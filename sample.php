<?php
require('./class.db.php');

$db = new db();
// $db->select('*');
// $db->from('site_info');
// $db->where('site_id',1);
// $db->limit(1);
// $db->order_by('site_id','ASC');

// $results = $db->query();

$data = array(
    'field'     => 'coreytest',
    'value'     => 'shawtest',
    'ts'        => '2010-03-16 13:43:06',
    'Comment'   => 'This is a test and will be deleted'
);
$table = 'info';
$insert_id = $db->insert($table,$data);

$db->print_array($insert_id);

?>