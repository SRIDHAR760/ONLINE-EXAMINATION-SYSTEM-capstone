<?php
// ============================================================
// dash.php  –  Admin dashboard
// Changes: role check at top, session timeout, status badges,
//          start_time field in Add Quiz, Security Logs menu
// ============================================================

// ── PHP GUARD (runs before any HTML) ─────────────────────────────────────────
require_once __DIR__ . '/dbConnection.php';
require_once __DIR__ . '/crypto.php';

session_start();
check_session_timeout();  // 15-min inactivity (Part 1)

// Role-based redirect: non-admins go to index.php (Part 1)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("location:index.php");
    exit;
}

$email = $_SESSION['email'] ?? '';
$name  = $_SESSION['name']  ?? 'Admin';
$q     = $_GET['q'] ?? '0';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Project Worlds || DASHBOARD </title>
<link  rel="stylesheet" href="css/bootstrap.min.css"/>
 <link  rel="stylesheet" href="css/bootstrap-theme.min.css"/>    
 <link rel="stylesheet" href="css/main.css">
 <link  rel="stylesheet" href="css/font.css">
 <script src="js/jquery.js" type="text/javascript"></script>

  <script src="js/bootstrap.min.js"  type="text/javascript"></script>
 	<link href='http://fonts.googleapis.com/css?family=Roboto:400,700,300' rel='stylesheet' type='text/css'>

<script>
$(function () {
    $(document).on( 'scroll', function(){
        console.log('scroll top : ' + $(window).scrollTop());
        if($(window).scrollTop()>=$(".logo").height())
        {
             $(".navbar").addClass("navbar-fixed-top");
        }

        if($(window).scrollTop()<$(".logo").height())
        {
             $(".navbar").removeClass("navbar-fixed-top");
        }
    });
});</script>
<style>
/* Status badges */
.badge-locked   { background-color: #f0ad4e; color:#fff; padding:3px 8px; border-radius:3px; font-size:12px; }
.badge-unlocked { background-color: #5cb85c; color:#fff; padding:3px 8px; border-radius:3px; font-size:12px; }
.badge-tampered { background-color: #d9534f; color:#fff; padding:3px 8px; border-radius:3px; font-size:12px; }
</style>
</head>

<body  style="background:#eee;">
<div class="header">
<div class="row">
<div class="col-lg-6">
<span class="logo">Test Your Skill</span></div>
<div class="col-md-4 col-md-offset-2">
<span class="pull-right top title1"><span class="log1"><span class="glyphicon glyphicon-user" aria-hidden="true"></span>&nbsp;&nbsp;&nbsp;&nbsp;Hello,</span> <a href="dash.php?q=0" class="log log1"><?php echo htmlspecialchars($name); ?></a>&nbsp;|&nbsp;<a href="logout.php?q=index.php" class="log"><span class="glyphicon glyphicon-log-out" aria-hidden="true"></span>&nbsp;Signout</a></span>
</div></div>
<!-- admin start-->

<!--navigation menu-->
<nav class="navbar navbar-default title1">
  <div class="container-fluid">
    <!-- Brand and toggle get grouped for better mobile display -->
    <div class="navbar-header">
      <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
        <span class="sr-only">Toggle navigation</span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
      <a class="navbar-brand" href="dash.php?q=0"><b>Dashboard</b></a>
    </div>
    <!-- Collect the nav links, forms, and other content for toggling -->
    <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
      <ul class="nav navbar-nav">
        <li <?php if($q==0) echo'class="active"'; ?>><a href="dash.php?q=0">Home<span class="sr-only">(current)</span></a></li>
        <li <?php if($q==1) echo'class="active"'; ?>><a href="dash.php?q=1">User</a></li>
		<li <?php if($q==2) echo'class="active"'; ?>><a href="dash.php?q=2">Ranking</a></li>
		<li <?php if($q==3) echo'class="active"'; ?>><a href="dash.php?q=3">Feedback</a></li>
        <li class="dropdown <?php if($q==4 || $q==5) echo'active'; ?>">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Quiz<span class="caret"></span></a>
          <ul class="dropdown-menu">
            <li><a href="dash.php?q=4">Add Quiz</a></li>
            <li><a href="dash.php?q=5">Remove Quiz</a></li>
		
          </ul>
        </li>
		<!-- Security Logs menu item (Part 6) -->
        <li <?php if($q==6) echo'class="active"'; ?>><a href="dash.php?q=6"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span>&nbsp;Security Logs</a></li>
		<li class="pull-right"> <a href="logout.php?q=index.php"><span class="glyphicon glyphicon-log-out" aria-hidden="true"></span>&nbsp;&nbsp;&nbsp;&nbsp;Signout</a></li>
		
      </ul>
          </div><!-- /.navbar-collapse -->
  </div><!-- /.container-fluid -->
</nav>
<!--navigation menu closed-->
<div class="container"><!--container start-->
<div class="row">
<div class="col-md-12">
<!--home start-->

<?php if($q==0): ?>
<?php
// Home: list all quizzes with status badge and start_time (Part 6)
$result = $con->query("SELECT * FROM quiz ORDER BY date DESC") or die('Error');
echo '<div class="panel"><div class="table-responsive"><table class="table table-striped title1">
<tr><td><b>S.N.</b></td><td><b>Topic</b></td><td><b>Total Q</b></td><td><b>Marks</b></td><td><b>Time</b></td><td><b>Start Time</b></td><td><b>Status</b></td><td></td></tr>';
$c = 1;
while ($row = mysqli_fetch_array($result)) {
    $title      = htmlspecialchars($row['title']);
    $total      = $row['total'];
    $sahi       = $row['sahi'];
    $time       = $row['time'];
    $eid        = $row['eid'];
    $status     = $row['status'];
    $start_time = $row['start_time'] ? date('d-m-Y H:i', strtotime($row['start_time'])) : '—';

    // Status badge
    $badge = match($status) {
        'unlocked' => '<span class="badge-unlocked">Unlocked</span>',
        'tampered' => '<span class="badge-tampered">Tampered</span>',
        default    => '<span class="badge-locked">Locked</span>',
    };

    // History check
    $stmt = $con->prepare("SELECT score FROM history WHERE eid=? AND email=?");
    $stmt->bind_param('ss', $eid, $email);
    $stmt->execute();
    $done = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$done) {
        $btn = $status === 'unlocked'
            ? '<a href="account.php?q=quiz&step=2&eid='.$eid.'&n=1&t='.$total.'" class="pull-right btn sub1" style="margin:0px;background:#99cc32"><span class="glyphicon glyphicon-new-window"></span>&nbsp;<b>Start</b></a>'
            : '';
    } else {
        $btn = $status === 'unlocked'
            ? '<a href="update.php?q=quizre&step=25&eid='.$eid.'&n=1&t='.$total.'" class="pull-right btn sub1" style="margin:0px;background:red"><span class="glyphicon glyphicon-repeat"></span>&nbsp;<b>Restart</b></a>'
            : '';
    }

    echo '<tr><td>'.$c++.'</td><td>'.$title.'</td><td>'.$total.'</td><td>'.($sahi*$total).'</td><td>'.$time.'&nbsp;min</td><td>'.$start_time.'</td><td>'.$badge.'</td><td>'.$btn.'</td></tr>';
}
echo '</table></div></div>';
?>
<?php endif; ?>

<!--home closed-->

<!--ranking start-->
<?php if($q==2): ?>
<?php
$stmt = $con->prepare("SELECT r.email, r.score, u.name, u.gender, u.college FROM rank r LEFT JOIN user u ON r.email=u.email ORDER BY r.score DESC");
$stmt->execute();
$rankres = $stmt->get_result();
$stmt->close();
echo '<div class="panel title"><div class="table-responsive">
<table class="table table-striped title1">
<tr style="color:red"><td><b>Rank</b></td><td><b>Name</b></td><td><b>Gender</b></td><td><b>College</b></td><td><b>Score</b></td></tr>';
$c = 0;
while ($row = $rankres->fetch_assoc()) {
    $c++;
    echo '<tr><td style="color:#99cc32"><b>'.$c.'</b></td><td>'.htmlspecialchars($row['name']).'</td><td>'.htmlspecialchars($row['gender']).'</td><td>'.htmlspecialchars($row['college']).'</td><td>'.$row['score'].'</td></tr>';
}
echo '</table></div></div>';
?>
<?php endif; ?>

<!--users start-->
<?php if($q==1): ?>
<?php
$result = $con->query("SELECT * FROM user") or die('Error');
echo '<div class="panel"><div class="table-responsive"><table class="table table-striped title1">
<tr><td><b>S.N.</b></td><td><b>Name</b></td><td><b>Gender</b></td><td><b>College</b></td><td><b>Email</b></td><td><b>Mobile</b></td><td></td></tr>';
$c = 1;
while ($row = mysqli_fetch_array($result)) {
    $name    = htmlspecialchars($row['name']);
    $mob     = htmlspecialchars($row['mob']);
    $gender  = htmlspecialchars($row['gender']);
    $em      = htmlspecialchars($row['email']);
    $college = htmlspecialchars($row['college']);
    echo '<tr><td>'.$c++.'</td><td>'.$name.'</td><td>'.$gender.'</td><td>'.$college.'</td><td>'.$em.'</td><td>'.$mob.'</td>
	<td><a title="Delete User" href="update.php?demail='.$row['email'].'"><b><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></b></a></td></tr>';
}
echo '</table></div></div>';
?>
<?php endif; ?>
<!--user end-->

<!--feedback start-->
<?php if($q==3): ?>
<?php
$result = $con->query("SELECT * FROM `feedback` ORDER BY `feedback`.`date` DESC") or die('Error');
echo '<div class="panel"><div class="table-responsive"><table class="table table-striped title1">
<tr><td><b>S.N.</b></td><td><b>Subject</b></td><td><b>Email</b></td><td><b>Date</b></td><td><b>Time</b></td><td><b>By</b></td><td></td><td></td></tr>';
$c = 1;
while ($row = mysqli_fetch_array($result)) {
    $date    = date("d-m-Y", strtotime($row['date']));
    $time    = htmlspecialchars($row['time']);
    $subject = htmlspecialchars($row['subject']);
    $fname   = htmlspecialchars($row['name']);
    $femail  = htmlspecialchars($row['email']);
    $id      = $row['id'];
    echo '<tr><td>'.$c++.'</td>';
    echo '<td><a title="Click to open feedback" href="dash.php?q=3&fid='.$id.'">'.$subject.'</a></td><td>'.$femail.'</td><td>'.$date.'</td><td>'.$time.'</td><td>'.$fname.'</td>
	<td><a title="Open Feedback" href="dash.php?q=3&fid='.$id.'"><b><span class="glyphicon glyphicon-folder-open" aria-hidden="true"></span></b></a></td>';
    echo '<td><a title="Delete Feedback" href="update.php?fdid='.$id.'"><b><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></b></a></td>
</tr>';
}
echo '</table></div></div>';
?>
<?php endif; ?>
<!--feedback closed-->

<!--feedback reading portion start-->
<?php if(isset($_GET['fid'])): ?>
<?php
echo '<br />';
$id = $_GET['fid'];
$stmt = $con->prepare("SELECT * FROM feedback WHERE id = ?");
$stmt->bind_param('s', $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
while ($row = $result->fetch_assoc()) {
    $fname    = htmlspecialchars($row['name']);
    $subject  = htmlspecialchars($row['subject']);
    $date     = date("d-m-Y", strtotime($row['date']));
    $time     = htmlspecialchars($row['time']);
    $feedback = htmlspecialchars($row['feedback']);
    echo '<div class="panel"><a title="Back to Archive" href="dash.php?q=3"><b><span class="glyphicon glyphicon-level-up" aria-hidden="true"></span></b></a><h2 style="text-align:center; margin-top:-15px;"><b>'.$subject.'</b></h2>';
    echo '<div style="margin-left:10px;margin-right:10px; max-height:450px; line-height:35px;padding:5px;"><span style="line-height:35px;padding:5px;">-&nbsp;<b>DATE:</b>&nbsp;'.$date.'</span>
<span style="line-height:35px;padding:5px;">&nbsp;<b>Time:</b>&nbsp;'.$time.'</span><span style="line-height:35px;padding:5px;">&nbsp;<b>By:</b>&nbsp;'.$fname.'</span><br />'.$feedback.'</div></div>';
}
?>
<?php endif; ?>
<!--Feedback reading portion closed-->

<!--add quiz start (step 1) – includes start_time field (Part 4) -->
<?php if($q==4 && !isset($_GET['step'])): ?>
<div class="row">
<span class="title1" style="margin-left:40%;font-size:30px;"><b>Enter Quiz Details</b></span><br /><br />
 <div class="col-md-3"></div><div class="col-md-6">   <form class="form-horizontal title1" name="form" action="update.php?q=addquiz"  method="POST">
<fieldset>

<!-- Text input-->
<div class="form-group">
  <label class="col-md-12 control-label" for="name"></label>  
  <div class="col-md-12">
  <input id="name" name="name" placeholder="Enter Quiz title" class="form-control input-md" type="text" required>
  </div>
</div>

<!-- Text input-->
<div class="form-group">
  <label class="col-md-12 control-label" for="total"></label>  
  <div class="col-md-12">
  <input id="total" name="total" placeholder="Enter total number of questions" class="form-control input-md" type="number" required>
  </div>
</div>

<!-- Text input-->
<div class="form-group">
  <label class="col-md-12 control-label" for="right"></label>  
  <div class="col-md-12">
  <input id="right" name="right" placeholder="Enter marks on right answer" class="form-control input-md" min="0" type="number" required>
  </div>
</div>

<!-- Text input-->
<div class="form-group">
  <label class="col-md-12 control-label" for="wrong"></label>  
  <div class="col-md-12">
  <input id="wrong" name="wrong" placeholder="Enter minus marks on wrong answer without sign" class="form-control input-md" min="0" type="number" required>
  </div>
</div>

<!-- Text input-->
<div class="form-group">
  <label class="col-md-12 control-label" for="time"></label>  
  <div class="col-md-12">
  <input id="time" name="time" placeholder="Enter time limit for test in minute" class="form-control input-md" min="1" type="number" required>
  </div>
</div>

<!-- Text input-->
<div class="form-group">
  <label class="col-md-12 control-label" for="tag"></label>  
  <div class="col-md-12">
  <input id="tag" name="tag" placeholder="Enter #tag which is used for searching" class="form-control input-md" type="text">
  </div>
</div>

<!-- START DATE/TIME field (Part 4) -->
<div class="form-group">
  <label class="col-md-12 control-label title1" for="start_time"><b>Scheduled Start Date &amp; Time (leave blank to lock indefinitely)</b></label>
  <div class="col-md-12">
  <input id="start_time" name="start_time" class="form-control input-md" type="datetime-local">
  <span style="font-size:12px;color:#888;">Server time: <?php echo date('Y-m-d H:i'); ?></span>
  </div>
</div>

<!-- Text input-->
<div class="form-group">
  <label class="col-md-12 control-label" for="desc"></label>  
  <div class="col-md-12">
  <textarea rows="8" cols="8" name="desc" class="form-control" placeholder="Write description here..."></textarea>  
  </div>
</div>


<div class="form-group">
  <label class="col-md-12 control-label" for=""></label>
  <div class="col-md-12"> 
    <input  type="submit" style="margin-left:45%" class="btn btn-primary" value="Submit" />
  </div>
</div>

</fieldset>
</form></div>


<?php endif; ?>
<!--add quiz end-->

<!--add quiz step2 start-->
<?php if($q==4 && isset($_GET['step']) && $_GET['step']==2): ?>
<div class="row">
<span class="title1" style="margin-left:40%;font-size:30px;"><b>Enter Question Details</b></span><br /><br />
 <div class="col-md-3"></div><div class="col-md-6"><form class="form-horizontal title1" name="form" action="update.php?q=addqns&n=<?php echo (int)$_GET['n']; ?>&eid=<?php echo htmlspecialchars($_GET['eid']); ?>&ch=4"  method="POST">
<fieldset>
<?php
$n = (int)($_GET['n'] ?? 0);
for ($i = 1; $i <= $n; $i++) {
echo '<b>Question number&nbsp;'.$i.'&nbsp;:</b><br /><!-- Text input-->
<div class="form-group">
  <label class="col-md-12 control-label" for="qns'.$i.' "></label>  
  <div class="col-md-12">
  <textarea rows="3" cols="5" name="qns'.$i.'" class="form-control" placeholder="Write question number '.$i.' here..."></textarea>  
  </div>
</div>
<!-- Text input-->
<div class="form-group">
  <label class="col-md-12 control-label" for="'.$i.'1"></label>  
  <div class="col-md-12">
  <input id="'.$i.'1" name="'.$i.'1" placeholder="Enter option a" class="form-control input-md" type="text">
  </div>
</div>
<!-- Text input-->
<div class="form-group">
  <label class="col-md-12 control-label" for="'.$i.'2"></label>  
  <div class="col-md-12">
  <input id="'.$i.'2" name="'.$i.'2" placeholder="Enter option b" class="form-control input-md" type="text">
  </div>
</div>
<!-- Text input-->
<div class="form-group">
  <label class="col-md-12 control-label" for="'.$i.'3"></label>  
  <div class="col-md-12">
  <input id="'.$i.'3" name="'.$i.'3" placeholder="Enter option c" class="form-control input-md" type="text">
  </div>
</div>
<!-- Text input-->
<div class="form-group">
  <label class="col-md-12 control-label" for="'.$i.'4"></label>  
  <div class="col-md-12">
  <input id="'.$i.'4" name="'.$i.'4" placeholder="Enter option d" class="form-control input-md" type="text">
  </div>
</div>
<br />
<b>Correct answer</b>:<br />
<select id="ans'.$i.'" name="ans'.$i.'" placeholder="Choose correct answer" class="form-control input-md">
   <option value="a">Select answer for question '.$i.'</option>
  <option value="a">option a</option>
  <option value="b">option b</option>
  <option value="c">option c</option>
  <option value="d">option d</option></select><br /><br />';
}
?>
<div class="form-group">
  <label class="col-md-12 control-label" for=""></label>
  <div class="col-md-12"> 
    <input  type="submit" style="margin-left:45%" class="btn btn-primary" value="Submit" />
  </div>
</div>

</fieldset>
</form></div>

<?php endif; ?><!--add quiz step 2 end-->

<!--remove quiz-->
<?php if($q==5): ?>
<?php
$result = $con->query("SELECT * FROM quiz ORDER BY date DESC") or die('Error');
echo '<div class="panel"><div class="table-responsive"><table class="table table-striped title1">
<tr><td><b>S.N.</b></td><td><b>Topic</b></td><td><b>Total Q</b></td><td><b>Marks</b></td><td><b>Time</b></td><td><b>Status</b></td><td></td></tr>';
$c = 1;
while ($row = mysqli_fetch_array($result)) {
    $title  = htmlspecialchars($row['title']);
    $total  = $row['total'];
    $sahi   = $row['sahi'];
    $time   = $row['time'];
    $eid    = $row['eid'];
    $status = $row['status'];
    $badge  = match($status) {
        'unlocked' => '<span class="badge-unlocked">Unlocked</span>',
        'tampered' => '<span class="badge-tampered">Tampered</span>',
        default    => '<span class="badge-locked">Locked</span>',
    };
    echo '<tr><td>'.$c++.'</td><td>'.$title.'</td><td>'.$total.'</td><td>'.($sahi*$total).'</td><td>'.$time.'&nbsp;min</td><td>'.$badge.'</td>
	<td><b><a href="update.php?q=rmquiz&eid='.$eid.'" class="pull-right btn sub1" style="margin:0px;background:red"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span>&nbsp;<span class="title1"><b>Remove</b></span></a></b></td></tr>';
}
echo '</table></div></div>';
?>
<?php endif; ?>

<!-- ============================================================
     SECURITY LOGS  (Part 6)
     ============================================================ -->
<?php if($q==6): ?>
<?php
// Quiz status counts
$counts = ['locked'=>0,'unlocked'=>0,'tampered'=>0];
$cr = $con->query("SELECT status, COUNT(*) c FROM quiz GROUP BY status");
while ($row = $cr->fetch_assoc()) {
    if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['c'];
}

echo '<div class="panel" style="padding:15px;">
<h3 class="title1">Quiz Status Overview</h3>
<div class="row" style="margin-bottom:15px;">
  <div class="col-md-3"><div style="background:#f0ad4e;color:#fff;padding:15px;border-radius:5px;text-align:center;"><h4>Locked</h4><h2>'.$counts['locked'].'</h2></div></div>
  <div class="col-md-3"><div style="background:#5cb85c;color:#fff;padding:15px;border-radius:5px;text-align:center;"><h4>Unlocked</h4><h2>'.$counts['unlocked'].'</h2></div></div>
  <div class="col-md-3"><div style="background:#d9534f;color:#fff;padding:15px;border-radius:5px;text-align:center;"><h4>Tampered</h4><h2>'.$counts['tampered'].'</h2></div></div>
</div>
</div>';

// Activity log table
$logres = $con->query("SELECT al.email, al.action, al.eid, al.ip, al.created, q.title
                        FROM activity_log al
                        LEFT JOIN quiz q ON al.eid = q.eid
                        ORDER BY al.created DESC
                        LIMIT 200");
echo '<div class="panel"><div class="table-responsive">
<h3 class="title1">Activity Log (latest 200)</h3>
<table class="table table-striped title1">
<tr style="color:red"><td><b>#</b></td><td><b>Email</b></td><td><b>Action</b></td><td><b>Quiz</b></td><td><b>IP</b></td><td><b>Date/Time</b></td></tr>';
$c = 1;
while ($row = $logres->fetch_assoc()) {
    $act_color = '';
    if ($row['action'] === 'TAMPER_DETECTED')      $act_color = 'color:red;font-weight:bold;';
    elseif ($row['action'] === 'EARLY_ACCESS_ATTEMPT') $act_color = 'color:orange;font-weight:bold;';
    elseif ($row['action'] === 'QUIZ_UNLOCKED')    $act_color = 'color:green;';
    echo '<tr><td>'.$c++.'</td>
    <td>'.htmlspecialchars($row['email']).'</td>
    <td style="'.$act_color.'">'.htmlspecialchars($row['action']).'</td>
    <td>'.htmlspecialchars($row['title'] ?? ($row['eid'] ?? '—')).'</td>
    <td>'.htmlspecialchars($row['ip']).'</td>
    <td>'.$row['created'].'</td></tr>';
}
echo '</table></div></div>';
?>
<?php endif; ?>


</div><!--container closed-->
</div></div>
</body>
</html>
