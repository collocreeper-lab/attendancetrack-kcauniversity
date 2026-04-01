<?php
// ============================================================
// AttendanceTrack — API Backend
// Place this file in: C:/xampp/htdocs/attendancetrack/api.php
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

// ============ DB CONFIG — Match your phpMyAdmin settings ============
define('DB_HOST', 'localhost');
define('DB_NAME', 'attendancetrack');
define('DB_USER', 'root');
define('DB_PASS', '');
// ====================================================================

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(['success'=>false,'error'=>'DB Connection failed: '.$conn->connect_error]));
    }
    $conn->set_charset('utf8');
    return $conn;
}

function sendEmail($to, $subject, $body) {
    // Requires PHP mail() or a mail library configured in XAMPP
    // For real email, configure php.ini SMTP settings
    $headers = "From: no-reply@kcau.ac.ke\r\nContent-Type: text/html\r\n";
    @mail($to, $subject, $body, $headers);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------------
    case 'login':
        $db = getDB();
        // Keep the original username value (with spaces) for exact match
        $username_raw = trim($input['username']);
        $password_raw = $input['password']; // do NOT trim — compare as-is and also trimmed
        $role = strtolower(trim($input['role']));

        // Search case-insensitively to handle "collo creeper" vs "Collo Creeper"
        $username_esc = $db->real_escape_string($username_raw);
        $res = $db->query("SELECT * FROM users WHERE LOWER(username)=LOWER('$username_esc')");

        if (!$res || $res->num_rows === 0) {
            echo json_encode(['success'=>false,'error'=>'❌ Username not found. Check spelling and spaces.']);
            break;
        }
        $user = $res->fetch_assoc();

        if (strtolower(trim($user['role'])) !== $role) {
            echo json_encode(['success'=>false,'error'=>'❌ You are registered as "' . $user['role'] . '". Please select that role.']);
            break;
        }

        // Compare password: try exact, then trimmed both sides
        $dbPass = $user['password'];
        $passMatch = ($dbPass === $password_raw) || (trim($dbPass) === trim($password_raw));
        if (!$passMatch) {
            echo json_encode(['success'=>false,'error'=>'❌ Incorrect password. Check for spaces or capitals.']);
            break;
        }

        echo json_encode(['success'=>true,'user'=>['username'=>$user['username'],'role'=>strtolower(trim($user['role'])),'user_id'=>$user['user_id']]]);
        break;

    // ----------------------------------------------------------------
    case 'studentStats':
        $db = getDB();
        $username = $db->real_escape_string($input['username']);
        $res = $db->query("SELECT student_id FROM students WHERE LOWER(username)=LOWER('$username')");
        if (!$res || $res->num_rows === 0) { echo json_encode(['success'=>false,'error'=>'Student not found in students table. Ask admin to add you.']); break; }
        $sid = $res->fetch_assoc()['student_id'];
        $r = $db->query("SELECT status, COUNT(*) as cnt, COALESCE(SUM(marks_deducted),0) as ded FROM attendance_records WHERE student_id=$sid GROUP BY status");
        $data = ['total'=>0,'present'=>0,'late'=>0,'absent'=>0,'deducted'=>0];
        while ($row = $r->fetch_assoc()) {
            $data[strtolower($row['status'])] = (int)$row['cnt'];
            if ($row['ded'] > 0) $data['deducted'] += (int)$row['ded'];
        }
        $data['total'] = $data['present']+$data['late']+$data['absent'];
        echo json_encode(['success'=>true,'data'=>$data]);
        break;

    // ----------------------------------------------------------------
    case 'activeSession':
        $db = getDB();
        $r = $db->query("SELECT s.*, c.course_code, c.course_name FROM attendance_sessions s JOIN courses c ON s.course_id=c.course_id WHERE s.status='OPEN' LIMIT 1");
        if ($r && $r->num_rows > 0) {
            $sess = $r->fetch_assoc();
            echo json_encode(['success'=>true,'session'=>$sess]);
        } else {
            echo json_encode(['success'=>false]);
        }
        break;

    // ----------------------------------------------------------------
    case 'markAttendance':
        $db = getDB();
        $username = $db->real_escape_string($input['username']);

        // Get student
        $res = $db->query("SELECT * FROM students WHERE LOWER(username)=LOWER('$username')");
        if (!$res || $res->num_rows === 0) { echo json_encode(['success'=>false,'error'=>'❌ Student not found in students table']); break; }
        $student = $res->fetch_assoc();
        $studentId = $student['student_id'];

        // Get open session
        $res = $db->query("SELECT * FROM attendance_sessions WHERE status='OPEN' LIMIT 1");
        if (!$res || $res->num_rows === 0) { echo json_encode(['success'=>false,'error'=>'❌ No active attendance session']); break; }
        $session = $res->fetch_assoc();
        $sessionId = $session['session_id'];

        // Duplicate check
        $res = $db->query("SELECT * FROM attendance_records WHERE student_id=$studentId AND session_id=$sessionId");
        if ($res && $res->num_rows > 0) { echo json_encode(['success'=>false,'error'=>'⚠️ You already signed attendance']); break; }

        // Time logic
        $now = date('H:i:s');
        $lateTime = $session['late_time'];
        $endTime = $session['end_time'];
        $status = 'PRESENT'; $marks = 0;
        if ($now >= $lateTime && $now < $endTime) { $status='LATE'; $marks=2; }
        elseif ($now >= $endTime) { $status='ABSENT'; $marks=5; }

        // Auto-close
        if ($now >= $endTime) {
            $db->query("UPDATE attendance_sessions SET status='CLOSED' WHERE session_id=$sessionId");
        }

        // Insert record
        $ts = date('Y-m-d H:i:s');
        $db->query("INSERT INTO attendance_records(student_id,session_id,time_signed,status,marks_deducted) VALUES($studentId,$sessionId,'$ts','$status',$marks)");

        // Check absent count → send email if >= 3
        $absentRes = $db->query("SELECT COUNT(*) as cnt FROM attendance_records WHERE student_id=$studentId AND status='ABSENT'");
        $absentCount = $absentRes->fetch_assoc()['cnt'];
        if ($absentCount >= 3 && !empty($student['email'])) {
            $emailBody = "<h2>Attendance Alert — KCA University</h2>
                <p>Dear {$student['full_name']},</p>
                <p>You have been marked <strong>ABSENT $absentCount times</strong>. This may result in academic penalties.</p>
                <p>Please ensure regular attendance. Each absence deducts <strong>5 marks</strong> from your total.</p>
                <p>— AttendanceTrack System, KCA University</p>";
            sendEmail($student['email'], 'Attendance Warning — KCA University', $emailBody);
        }

        $msg = "✅ Attendance marked: $status" . ($marks>0 ? " (Penalty: -$marks marks)" : "");
        echo json_encode(['success'=>true,'message'=>$msg]);
        break;

    // ----------------------------------------------------------------
    case 'studentRecords':
        $db = getDB();
        $username = $db->real_escape_string($input['username']);
        $res = $db->query("SELECT student_id FROM students WHERE LOWER(username)=LOWER('$username')");
        if (!$res||$res->num_rows===0){echo json_encode(['success'=>false,'records'=>[]]);break;}
        $sid = $res->fetch_assoc()['student_id'];
        $r = $db->query("SELECT ar.*, c.course_code, c.course_name, DATE(ar.time_signed) as date
            FROM attendance_records ar
            JOIN attendance_sessions s ON ar.session_id=s.session_id
            JOIN courses c ON s.course_id=c.course_id
            WHERE ar.student_id=$sid ORDER BY ar.time_signed DESC");
        $records = [];
        while($row=$r->fetch_assoc()) $records[]=$row;
        echo json_encode(['success'=>true,'records'=>$records]);
        break;

    // ----------------------------------------------------------------
    case 'lecturerCourses':
        $db = getDB();
        $username = $db->real_escape_string($input['username']);
        $res = $db->query("SELECT user_id FROM users WHERE username='$username' AND role='lecturer'");
        if(!$res||$res->num_rows===0){echo json_encode(['success'=>false,'courses'=>[]]);break;}
        $lid = $res->fetch_assoc()['user_id'];
        $r = $db->query("SELECT c.*, (SELECT COUNT(*) FROM attendance_sessions WHERE course_id=c.course_id) as session_count
            FROM courses c WHERE c.lecturer_id=$lid");
        $courses=[];
        while($row=$r->fetch_assoc()) $courses[]=$row;
        echo json_encode(['success'=>true,'courses'=>$courses]);
        break;

    // ----------------------------------------------------------------
    case 'openSession':
        $db = getDB();
        $courseId = (int)$input['course_id'];
        $lateTime = $db->real_escape_string($input['late_time']);
        $endTime = $db->real_escape_string($input['end_time']);
        $today = date('Y-m-d');
        $db->query("INSERT INTO attendance_sessions(course_id,date_opened,late_time,end_time,status) VALUES($courseId,'$today','$lateTime','$endTime','OPEN')");
        echo json_encode(['success'=>true]);
        break;

    // ----------------------------------------------------------------
    case 'allSessions':
        $db = getDB();
        $username = $db->real_escape_string($input['username']);
        $res = $db->query("SELECT user_id FROM users WHERE username='$username' AND role='lecturer'");
        if(!$res||$res->num_rows===0){echo json_encode(['success'=>false,'sessions'=>[]]);break;}
        $lid = $res->fetch_assoc()['user_id'];
        $r = $db->query("SELECT s.*, c.course_code FROM attendance_sessions s JOIN courses c ON s.course_id=c.course_id WHERE c.lecturer_id=$lid ORDER BY s.date_opened DESC");
        $sessions=[];
        while($row=$r->fetch_assoc()) $sessions[]=$row;
        echo json_encode(['success'=>true,'sessions'=>$sessions]);
        break;

    // ----------------------------------------------------------------
    case 'closeSession':
        $db = getDB();
        $sid = (int)$input['session_id'];
        $db->query("UPDATE attendance_sessions SET status='CLOSED' WHERE session_id=$sid");
        echo json_encode(['success'=>true]);
        break;

    // ----------------------------------------------------------------
    case 'attendanceReport':
        $db = getDB();
        $courseId = (int)$input['course_id'];
        // Get all students enrolled (or all students)
        $r = $db->query("SELECT DISTINCT st.student_id, st.username, st.full_name FROM students st
            LEFT JOIN attendance_records ar ON st.student_id=ar.student_id
            LEFT JOIN attendance_sessions sess ON ar.session_id=sess.session_id AND sess.course_id=$courseId
            WHERE sess.course_id=$courseId OR st.student_id IS NOT NULL
            GROUP BY st.student_id ORDER BY st.full_name");
        // Simpler: get all students and their stats for this course
        $r2 = $db->query("SELECT st.student_id, st.username, st.full_name,
            SUM(CASE WHEN ar.status='PRESENT' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN ar.status='LATE' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN ar.status='ABSENT' THEN 1 ELSE 0 END) as absent,
            COALESCE(SUM(ar.marks_deducted),0) as deducted,
            COUNT(ar.record_id) as total
            FROM students st
            LEFT JOIN attendance_records ar ON st.student_id=ar.student_id
            LEFT JOIN attendance_sessions sess ON ar.session_id=sess.session_id AND sess.course_id=$courseId
            GROUP BY st.student_id ORDER BY st.full_name");
        $students=[];
        while($row=$r2->fetch_assoc()) $students[]=$row;
        echo json_encode(['success'=>true,'students'=>$students]);
        break;

    // ----------------------------------------------------------------
    case 'adminOverview':
        $db = getDB();
        $students = $db->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'];
        $lecturers = $db->query("SELECT COUNT(*) as c FROM users WHERE role='lecturer'")->fetch_assoc()['c'];
        $courses = $db->query("SELECT COUNT(*) as c FROM courses")->fetch_assoc()['c'];
        $sessions = $db->query("SELECT COUNT(*) as c FROM attendance_sessions")->fetch_assoc()['c'];
        $total_records = $db->query("SELECT COUNT(*) as c FROM attendance_records")->fetch_assoc()['c'];
        $present_count = $db->query("SELECT COUNT(*) as c FROM attendance_records WHERE status='PRESENT'")->fetch_assoc()['c'];
        $absent_count = $db->query("SELECT COUNT(*) as c FROM attendance_records WHERE status='ABSENT'")->fetch_assoc()['c'];
        $late_count = $db->query("SELECT COUNT(*) as c FROM attendance_records WHERE status='LATE'")->fetch_assoc()['c'];
        // At-risk: absent >= 3
        $at_risk = $db->query("SELECT COUNT(DISTINCT student_id) as c FROM attendance_records WHERE status='ABSENT' GROUP BY student_id HAVING COUNT(*)>=3")->num_rows;
        // Weekly (Mon-Fri this week)
        $weekly=[];
        for($i=1;$i<=5;$i++){
            $day=date('Y-m-d',strtotime("this week monday +".($i-1)." days"));
            $r=$db->query("SELECT COUNT(*) as c FROM attendance_records WHERE DATE(time_signed)='$day' AND status IN ('PRESENT','LATE')");
            $present=$r->fetch_assoc()['c'];
            $r2=$db->query("SELECT COUNT(*) as c FROM attendance_records WHERE DATE(time_signed)='$day'");
            $tot=$r2->fetch_assoc()['c'];
            $weekly[]=$tot>0?round($present/$tot*100):0;
        }
        // All records
        $recs=$db->query("SELECT ar.*, u.username, st.full_name, c.course_code FROM attendance_records ar
            JOIN students st ON ar.student_id=st.student_id
            JOIN users u ON st.username=u.username
            JOIN attendance_sessions sess ON ar.session_id=sess.session_id
            JOIN courses c ON sess.course_id=c.course_id
            ORDER BY ar.time_signed DESC LIMIT 50");
        $records=[];
        while($row=$recs->fetch_assoc()) $records[]=$row;
        echo json_encode(['success'=>true,'data'=>compact('students','lecturers','courses','sessions','total_records','present_count','absent_count','late_count','at_risk','weekly'),'records'=>$records]);
        break;

    // ----------------------------------------------------------------
    case 'allUsers':
        $db = getDB();
        $r = $db->query("SELECT u.user_id, u.username, u.role, st.full_name, st.admission_number, st.email, st.phone
            FROM users u LEFT JOIN students st ON u.username=st.username ORDER BY u.role, u.username");
        $users=[];
        while($row=$r->fetch_assoc()) $users[]=$row;
        echo json_encode(['success'=>true,'users'=>$users]);
        break;

    // ----------------------------------------------------------------
    case 'addLecturer':
        $db = getDB();
        $username = $db->real_escape_string($input['username']);
        $password = $db->real_escape_string($input['password']);
        $check = $db->query("SELECT user_id FROM users WHERE username='$username'");
        if($check&&$check->num_rows>0){echo json_encode(['success'=>false,'error'=>'Username already exists']);break;}
        $db->query("INSERT INTO users(username,password,role) VALUES('$username','$password','lecturer')");
        // Also insert into lecturers table if it exists
        @$db->query("INSERT INTO lecturers(username) VALUES('$username')");
        echo json_encode(['success'=>true]);
        break;

    // ----------------------------------------------------------------
    case 'addStudent':
        $db = getDB();
        $username = $db->real_escape_string($input['username']);
        $password = $db->real_escape_string($input['password']);
        $full_name = $db->real_escape_string($input['full_name']??'');
        $admission_number = $db->real_escape_string($input['admission_number']??'');
        $email = $db->real_escape_string($input['email']??'');
        $phone = $db->real_escape_string($input['phone']??'');
        $check = $db->query("SELECT user_id FROM users WHERE username='$username'");
        if($check&&$check->num_rows>0){echo json_encode(['success'=>false,'error'=>'Username already exists']);break;}
        $db->query("INSERT INTO users(username,password,role) VALUES('$username','$password','student')");
        // Insert into students table — handle whether email/phone/admission columns exist
        $cols="username"; $vals="'$username'";
        if($full_name){$cols.=",full_name";$vals.=",'$full_name'";}
        if($admission_number){$cols.=",admission_number";$vals.=",'$admission_number'";}
        if($email){$cols.=",email";$vals.=",'$email'";}
        if($phone){$cols.=",phone";$vals.=",'$phone'";}
        $db->query("INSERT INTO students($cols) VALUES($vals)");
        echo json_encode(['success'=>true]);
        break;

    // ----------------------------------------------------------------
    case 'allCourses':
        $db = getDB();
        $r = $db->query("SELECT c.*, u.username as lecturer_username FROM courses c LEFT JOIN users u ON c.lecturer_id=u.user_id ORDER BY c.course_code");
        $courses=[];
        while($row=$r->fetch_assoc()) $courses[]=$row;
        echo json_encode(['success'=>true,'courses'=>$courses]);
        break;

    // ----------------------------------------------------------------
    case 'allLecturers':
        $db = getDB();
        $r = $db->query("SELECT user_id, username FROM users WHERE role='lecturer' ORDER BY username");
        $lecturers=[];
        while($row=$r->fetch_assoc()) $lecturers[]=$row;
        echo json_encode(['success'=>true,'lecturers'=>$lecturers]);
        break;

    // ----------------------------------------------------------------
    case 'addCourse':
        $db = getDB();
        $name = $db->real_escape_string($input['course_name']);
        $code = $db->real_escape_string($input['course_code']);
        $lecId = (int)$input['lecturer_id'];
        $sem = $db->real_escape_string($input['semester']??'');
        $check = $db->query("SELECT course_id FROM courses WHERE course_code='$code'");
        if($check&&$check->num_rows>0){echo json_encode(['success'=>false,'error'=>'Course code already exists']);break;}
        $db->query("INSERT INTO courses(course_name,course_code,lecturer_id,semester) VALUES('$name','$code',$lecId,'$sem')");
        echo json_encode(['success'=>true]);
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'Unknown action: '.$action]);
}
?>
