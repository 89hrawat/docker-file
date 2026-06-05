<?php

$master = new Redis();
$master->connect('redis-master', 6379);

$slave = new Redis();
$slave->connect('redis-slave', 6379);

$message = "";

/* INSERT STUDENT */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $id = trim($_POST['id']);
    $name = trim($_POST['name']);
    $course = trim($_POST['course']);

    $student = [
        'id'     => $id,
        'name'   => $name,
        'course' => $course
    ];

    $master->hMSet("student:$id", $student);

    $master->sAdd("students", $id);

    $message = "Student inserted into Redis Master";
}

/* READ FROM SLAVE */
$students = [];

$ids = $slave->sMembers("students");

foreach ($ids as $id) {
    $students[] = $slave->hGetAll("student:$id");
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Redis Master Slave Demo</title>
<style>
table{
border-collapse:collapse;
width:70%;
}
th,td{
border:1px solid black;
padding:8px;
}
</style>
</head>
<body>

<h2>Student Entry Form</h2>

<?php echo $message; ?>

<form method="POST">

Student ID:
<input type="text" name="id" required><br><br>

Name:
<input type="text" name="name" required><br><br>

Course:
<input type="text" name="course" required><br><br>

<input type="submit" value="Save To Redis Master">

</form>

<hr>

<h2>Student Records (Read From Slave)</h2>

<table>

<tr>
<th>ID</th>
<th>Name</th>
<th>Course</th>
</tr>

<?php foreach($students as $s): ?>

<tr>
<td><?php echo $s['id'] ?? ''; ?></td>
<td><?php echo $s['name'] ?? ''; ?></td>
<td><?php echo $s['course'] ?? ''; ?></td>
</tr>

<?php endforeach; ?>

</table>

</body>
</html>
