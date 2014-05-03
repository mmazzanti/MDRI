<?php
require_once 'MDRI_config.php';
require_once 'MDRI_get.php';
require_once 'MDRI_put.php';
require_once 'MDRI_head.php';
require_once 'MDRI_post.php';
require_once 'MDRI_delete.php';
require_once 'MDRI_database.php';

/**
 * Analyze the HTTP Method used from client and creating an istance of the respective object.
 * If HTTP method isn't one of these: GET, HEAD, POST, PUT, DELETE an error 405 (METHOD NOT ALLOWED) will be returned.
 */
if(isset($_SERVER['REQUEST_METHOD'])){
  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      $MDRI =new MDRI_get($config);
      $MDRI->init();
      break;
    case 'HEAD':
      $MDRI =new MDRI_head($config);
      $MDRI->init();
      break;
    case 'POST':
      $MDRI =new MDRI_post($config);
      $MDRI->init();
      break;
    case 'PUT':
      $MDRI =new MDRI_put($config);
      $MDRI->init();
      break;
    case 'DELETE':
      $MDRI =new MDRI_delete($config);
      $MDRI->init();
      break;
    default:
      header('HTTP/1.0 405 Method Not Allowed');
      break;
  }
}
else{
  header('HTTP/1.0 500 Internal Server Error');
}
exit(0);
?>