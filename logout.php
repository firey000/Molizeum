<?php
session_save_path(sys_get_temp_dir());
session_start();
session_destroy();
header("Location: login-form.php");
exit;
?>
