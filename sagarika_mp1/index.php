<html>
<head><link rel="stylesheet" href="css/style1.css"></head>
<body>

<div class="navigation">  
  <div class="navbar">  
   <ul class="nav">  
  	<li><a href="about.php">About</a></li>  
  	<li><a href="index.php">User Login</a></li>  
   </ul>
  </div>  
</div>


<form action="process.php" method="post" enctype="multipart/form-data">
	<div class="welcome">Welcome!</div><br><br>
	E-mail: <input type="text" name="email"><br>
	Cell-phone: <input type="text" name="phone"><br>
	Browse-image: <input type="file" name="file-upload"><br>
	<input type="submit" value = "Submit!">
</form>

<script> 
	sleep(1000);//sleep for 1 second = 1000 milliseconds
	window.location = 'process.php';
</script>

</body>
</html>
