<?php
ini_set('max_execution_time', 102400);
if(begin_moving())
{
	$meta_keyarray = array(	'data1',
							'data2',
							'data3',
							'data4',
							'data5',
							'displayenddate',
							'email',
							'highlighted',
							'iconid',
							'_wp_old_slug');
	$result = get_old_posts();
	// At $post_parent enter page ID that is assigned to page with slug 'jobs' OR get this ID using query "select ID from wp_posts where post_name='jobs';"
	$post_parent = 4; 
	$old_post_terms = custom_get_old_categories($result);
	insert_new_categories($old_post_terms);
	while($row = mysql_fetch_array($result))
	{
		$curr_post_id = custom_insert_job_post($row['post_title'],$row['post_name'],'jobman_job',$post_parent);
		foreach($meta_keyarray as $meta_key)
		{
			$current_key_meta_value = custom_get_post_meta($row['ID'],$meta_key);
			custom_add_post_meta($curr_post_id,$meta_key,$current_key_meta_value);
		}
		
		$current_post_terms = custom_get_current_post_categories($row['ID']);
		foreach($current_post_terms as $current_post_term)
		{
			$curr_tax_id = get_taxonomy_id_for_category($current_post_term);
			insert_new_taxonomy_relation($curr_tax_id,$curr_post_id);
		}
	}
	echo 'Job posting is completed';
}
else
{
		echo 'Job posting is already started or completed<br>From table "wp_options" remove "move_jobdb" to begin again.';
}

function get_old_posts()
{
	$conn1 = get_old_connection();
	$query1 = "select * from wp_posts where post_type='jobman_job' and  post_status in('publish');";
	$result =  mysql_query($query1);
	return $result;
	close_current_connection($conn1);
}

function custom_get_post_meta($current_post_id,$meta_key)
{
	$conn2 = get_old_connection();
	$q = "select meta_value from wp_postmeta where post_id=" . $current_post_id . " and meta_key='" . $meta_key . "';";
	$r =  mysql_query($q);
	$r1 = mysql_fetch_array( $r );
	return $r1['meta_value'];
	close_current_connection($conn2);
}

function custom_insert_job_post($curr_post_title,$curr_post_name,$curr_post_type,$post_parent)
{
	$conn3 = get_new_connection();
	$q = "insert into wp_posts (comment_status,ping_status,post_status,post_content,post_title,post_name,post_type,post_parent) values('closed','closed','publish','','" . $curr_post_title . "','" . $curr_post_name . "','" . $curr_post_type . "'," . $post_parent . ");";
	$r =  mysql_query($q);
	return mysql_insert_id();
	mysql_close($conn3);
}

function custom_get_old_categories($result_to_fetch_categories)
{
	$conn4 = get_old_connection();
	if($result_to_fetch_categories)
	{
		$post_rows = array();
		while($postrow = mysql_fetch_array( $result_to_fetch_categories ))
		{
			$post_rows[] = $postrow['ID'];			
		}
		$in_rows = implode("," ,$post_rows);
		$catquery = "SELECT distinct(name) FROM wp_terms
						INNER JOIN wp_term_taxonomy
						ON wp_terms.term_id = wp_term_taxonomy.term_id
						WHERE wp_term_taxonomy.taxonomy='jobman_category' and wp_term_taxonomy.term_taxonomy_id 
						IN ( 
							SELECT term_taxonomy_id FROM wp_term_relationships
							WHERE object_id in (select ID from wp_posts where id in (" . $in_rows . "))
						);";
		$catqueryres =  mysql_query($catquery);
		$old_post_categories = array();
		while($catqueryres_row = mysql_fetch_array( $catqueryres ))
		{
			$old_post_categories[] = $catqueryres_row['name'];
		}		
	}
	return $old_post_categories;
	mysql_close($conn4);
}

function custom_get_current_post_categories($current_post_id)
{
	$conn4 = get_old_connection();
	if($current_post_id)
	{
		$catquery = "SELECT distinct(name) FROM wp_terms
						INNER JOIN wp_term_taxonomy
						ON wp_terms.term_id = wp_term_taxonomy.term_id
						WHERE wp_term_taxonomy.taxonomy='jobman_category' and wp_term_taxonomy.term_taxonomy_id 
						IN ( 
							SELECT term_taxonomy_id FROM wp_term_relationships
							WHERE object_id in (select ID from wp_posts where id in (" . $current_post_id . "))
						);";
		$catqueryres =  mysql_query($catquery);
		$current_post_categories = array();
		while($catqueryres_row = mysql_fetch_array( $catqueryres ))
		{
			$current_post_categories[] = $catqueryres_row['name'];
		}		
	}
	return $current_post_categories;
	mysql_close($conn4);
}

function get_taxonomy_id_for_category($category_name)
{
	$conn4 = get_new_connection();
	$taxonmoy_query = "select wp_term_taxonomy.term_taxonomy_id from wp_term_taxonomy
					INNER join wp_terms
					on wp_term_taxonomy.term_id = wp_terms.term_id
					where wp_terms.name = '" . $category_name . "';";
	$taxonmoy_query_result =  mysql_query($taxonmoy_query);
	$taxonmoy_row = mysql_fetch_array( $taxonmoy_query_result );
	return $taxonmoy_row['term_taxonomy_id'];
	mysql_close($conn4);
}

function insert_new_taxonomy_relation($curr_tax,$curr_obj)
{
	$conn7 = get_new_connection();
	$new_taxquery = "INSERT INTO wp_term_relationships (term_taxonomy_id,object_id) VALUES(" . $curr_tax . "," . $curr_obj .");";
	mysql_query($new_taxquery);
	mysql_close($conn7);
}

function insert_new_categories($old_post_terms)
{
	$conn5 = get_new_connection();
	foreach($old_post_terms as $newcat)
	{
		$catslug = str_replace(" ","-",strtolower($newcat));
		$new_catquery = "INSERT INTO wp_terms (name,slug) VALUES('" . $newcat . "','" . $catslug . "');";
		mysql_query($new_catquery);
		$current_term_id = mysql_insert_id();
		$new_taxquery = "INSERT INTO wp_term_taxonomy (term_id,taxonomy) VALUES('" . $current_term_id . "','jobman_category');";
		mysql_query($new_taxquery);
	}
	mysql_close($conn5);
}


function begin_moving()
{
	$conn6 = get_new_connection();
	if(!begin_get_option())
	{
		$begin_moving_jobs = "INSERT INTO wp_options (option_name,option_value) VALUES('move_jobdb','true');";
		mysql_query($begin_moving_jobs);		
		return true;
	}
	else
	{
		return false;
	}
	close_current_connection($conn6);
}

function begin_get_option()
{
	$conn6 = get_new_connection();
	$query1 = "select option_value from wp_options where option_name='move_jobdb';";
	$result =  mysql_query($query1);
	$optionrow = mysql_fetch_array($result);
	return $optionrow['option_value'];
	close_current_connection($conn6);
}


function custom_add_post_meta($current_post_id,$meta_key,$meta_value)
{
	$conn3 = get_new_connection();
	$q = "INSERT INTO wp_postmeta (post_id,meta_key,meta_value) VALUES('" . $current_post_id . "','" . $meta_key . "','" . $meta_value . "');";
	$r =  mysql_query($q);
	return mysql_insert_id();
	mysql_close($conn3);
}

function get_old_connection()
{
	$conn = mysql_connect("localhost", "old_db_username", "olddbpassword") or die(mysql_error());
	mysql_select_db("old_wordpress_db") or die(mysql_error());
	return $conn;
}

function get_new_connection()
{
	$conn = mysql_connect("localhost", "new_db_username", "newdbpassword") or die(mysql_error());
	mysql_select_db("new_wordpress_db") or die(mysql_error());
	return $conn;
}

function close_current_connection($connection)
{
	mysql_close($connection);
}
?>
