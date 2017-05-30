<!doctype html>
<html>
<head></head>
<body>
<?php 

require_once $_SERVER['DOCUMENT_ROOT'].'/'.'vendor/autoload.php';
use GraphAware\Neo4j\Client\ClientBuilder;


$neo4j = ClientBuilder::create()->addConnection('default', 'http://neo4j:admin@localhost:7474')->build(); // Example for HTTP connection configuration (port is optional)	

$results_per_page=5;
$page = $_GET['page'];
$page--;
$skip = $page * $results_per_page;
		$query = "MATCH (n:Url)<-[r]-() WHERE NOT EXISTS(n.is404) AND n.type = 'internal'
	RETURN count(DISTINCT n)";

	$result1 = $neo4j->sendCypherQuery($query);
		
	foreach ($result1->getRecords() as $record1) {
		$count = $record1->value('count(DISTINCT n)');
	}
		
	$query = "MATCH (n)<-[r]-() WHERE NOT EXISTS(n.is404) AND n.type = 'internal'
	WITH n, count(r) as c
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[r2:has_item]->(i:Item)-[:has_property]->(title) WHERE g.group = 'title'
	WITH n, c, title
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[r2:has_item]->(i:Item)-[:has_property]->(description) WHERE g.group = 'description'
	RETURN n.href, c, title.content, description.content
	ORDER BY c DESC
	SKIP $skip
	LIMIT $results_per_page";
	$result = $neo4j->sendCypherQuery($query);

	foreach ($result->getRecords() as $record) {
		echo '<hr><div><a href="'.$record->value('n.href').'">'.($record->value('title.content') == ''? 'null' : $record->value('title.content')).'</a> <-links- '.$record->value('c').'<br>'.($record->value('description.content') == ''? 'null' : $record->value('description.content')).'</div>';
	}

$total_page_count = ceil($count / $results_per_page);
$i = 0;
while(++$i <= $total_page_count){	
$style='black';
if(($page + 1) == $i){
	$style='green';
}
?>

<a style="border: 2px solid <?php echo $style; ?>; font-size:18px;margin: 3px;padding: 2px;" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>

<?php	
}
?>
</body>
</html>

---------- OR ------------


<?php 

require_once $_SERVER['DOCUMENT_ROOT'].'/'.'vendor/autoload.php';
use GraphAware\Neo4j\Client\ClientBuilder;


$neo4j = ClientBuilder::create()->addConnection('default', 'http://neo4j:admin@localhost:7474')->build(); // Example for HTTP connection configuration (port is optional)	

$results_per_page=5;
$page = $_GET['page'];
$page--;
$skip = $page * $results_per_page;

	$query = "MATCH (n:Url)<-[r]-() WHERE NOT EXISTS(n.is404) AND n.type = 'internal'
	RETURN count(DISTINCT n)";

	$result1 = $neo4j->sendCypherQuery($query);
		
	foreach ($result1->getRecords() as $record1) {
		$count = $record1->value('count(DISTINCT n)');
	}
		
	/* just get all values
	$query = "MATCH (n)<-[r]-() WHERE NOT EXISTS(n.is404) AND n.type = 'internal'
	WITH n, count(r) as c

	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[r2:has_item]->(i:Item)-[:has_property]->(p) WHERE EXISTS(g.group)

	WITH n, c, Collect(i.itemID) AS items, Collect(g.group) AS groups, Collect(p) AS props

	RETURN n.href, c, Collect({items: items,groups: groups, p: props}) as itemlist

	ORDER BY c DESC
	*/
	//Pick and exclude
		
	$query = "MATCH (n)<-[r]-() WHERE NOT EXISTS(n.is404) AND n.type = 'internal'
	WITH n, count(r) as c
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[r2:has_item]->(i:Item)-[:has_property]->(title) WHERE g.group = 'title'
	WITH n, c, title
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[r2:has_item]->(i:Item)-[:has_property]->(description) WHERE g.group = 'description'
	WITH n, c, title, description
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[r2:has_item]->(i:Item)-[:has_property]->(p) WHERE NOT (g.group = 'title' OR g.group ='description' OR g.group ='a')

	WITH n, c, title, description, Collect(i.itemID) AS items, Collect(g.group) AS groups, Collect(p) AS props

	RETURN n.href, c, title.content, description.content, Collect({items: items,groups: groups, p: props}) as itemlist

	ORDER BY c DESC
	SKIP $skip
	LIMIT $results_per_page";
	$result = $neo4j->sendCypherQuery($query);
	
	$out='';
	foreach ($result->getRecords() as $record) {
		$out.='<hr><div class="page"><a href="'.$record->value('n.href').'"><h2>'
		.($record->value('title.content') == ''? 'null' : $record->value('title.content'))
		.'</a> <-links- '.$record->value('c')
		.'</h2>'
		.'<h3>description</h3>'.($record->value('description.content') == ''? 'null' : $record->value('description.content'))
		.'<br>';
		
		
		$groups=[];$subGroup=[];$properties=[];
		if($record->value('itemlist') !== ''){
			foreach($record->value('itemlist') as $key => $item){
				if($key=='items'){
					
			
					foreach($item as $k => $itemID){
						foreach( $itemID as $keys => $id){		
							if($k == 'groups'){
					
								$groups[]= $id;
								
							}
						}	
		
						
						
						foreach( $itemID as $keys => $id){		
							if($k == 'items'){
								$subGroup[$id][] =  $keys ;
							}
						}	
						
						foreach( $itemID as $keys => $id){		
							if($k == 'p'){
								foreach( $id as $content => $attr){		
									$properties[] = $attr;	
								}
								
							}
						}	
					
						
					}

					
					
				
				}			
			}
		}
		
		$new =[];
		foreach($subGroup as $key=> $value){
			foreach($value as $key2){
				foreach($properties as $pkey => $property){
					if($key2 == $pkey){
						foreach($groups as $gkey => $gp){
							if($pkey == $gkey){
								$new[$gp][$key][$key2]=$property;
							}
						}
						
						
					}
					
				}
			}
		}
				
				
			
		foreach($new as $group => $items){
			$out.='<div class="group">';
			$out.='<h3>'.$group.'</h3>';
			foreach($items as $item => $properties){
				$out.='<div class="item">';
				foreach($properties as $value){
					$out.='<div class="property">'.$value['property'].' : '.$value['content'].'</div>';
				}
				$out.='</div>';
				
			}
			$out.='</div>';
		}
		$out.='</div>';
	}

	
	
?>
<!doctype html>
<html>
<head>
<style>
.item {border: solid 1px grey;padding:1px 3px}
.page{border: 2px solid black;margin:5px;padding:5px;}
</style>
</head>
<body>
<?php	
$total_page_count = ceil($count / $results_per_page);
$i = 0;
while(++$i <= $total_page_count){	
$style='black';
if(($page + 1) == $i){
	$style='green';
}
?>
<a style="border: 2px solid <?php echo $style; ?>; font-size:18px;margin: 3px;padding: 2px;" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
<?php	
}

echo $count. ' Results';
?>


<hr>
<?php echo $out;?>
<hr>


<?php	

$i = 0;
while(++$i <= $total_page_count){	
$style='black';
if(($page + 1) == $i){
	$style='green';
}
?>
<a style="border: 2px solid <?php echo $style; ?>; font-size:18px;margin: 3px;padding: 2px;" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
<?php	
}
?>
</body>
</html>

---------- OR With Search------------
<?php 

require_once $_SERVER['DOCUMENT_ROOT'].'/'.'vendor/autoload.php';
use GraphAware\Neo4j\Client\ClientBuilder;


$neo4j = ClientBuilder::create()->addConnection('default', 'http://neo4j:admin@localhost:7474')->build(); // Example for HTTP connection configuration (port is optional)	

$results_per_page=5;
$page = $_GET['page'];
$page--;
$skip = $page * $results_per_page;


	$name = '';
	if(@$_GET['search'] !='') {
		$name = $_GET['search'];
	}else {
		$name = '';		 
	} 
	
	
	$query = "	
	MATCH ()-[r:references]->(n:Url)-[:has_group]->(g:Group)-[r2:has_item]->(i:Item)-[:has_property]->(p)
	WHERE ((p.content =~ '(?i).*".$name.".*' AND g.group = 'title')
	OR (p.content =~ '(?i).*".$name.".*' AND g.group = 'description')) 
	AND NOT EXISTS(n.is404) AND n.type = 'internal'
	RETURN count(DISTINCT n)";

	$result1 = $neo4j->sendCypherQuery($query);
		
	foreach ($result1->getRecords() as $record1) {
		$count = $record1->value('count(DISTINCT n)');
	}
		
	/* MATCH (n)-[r:has_group]->()-[]->()-[r2:has_property]->() RETURN n,r,r2 LIMIT 500
			$query = "MATCH (n)<-[r]-() WHERE NOT EXISTS(n.is404) AND n.type = 'internal'
	WITH n, count(r) as c

	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[r2:has_item]->(i:Item)-[:has_property]->(p) WHERE EXISTS(g.group)

	WITH n, c, Collect(i.itemID) AS items, Collect(g.group) AS groups, Collect(p) AS props

	RETURN n.href, c, Collect({items: items,groups: groups, p: props}) as itemlist

	ORDER BY c DESC
		*/

		
	$query = "
	MATCH ()-[r:references]->(n:Url)-[:has_group]->(g:Group)-[r2:has_item]->(i:Item)-[:has_property]->(p)
	WHERE ((p.content =~ '(?i).*".$name.".*' AND g.group = 'title')
	OR (p.content =~ '(?i).*".$name.".*' AND g.group = 'description')) 
	AND NOT EXISTS(n.is404) AND n.type = 'internal'

	WITH n, count(DISTINCT r) as c
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[r2:has_item]->(i:Item)-[:has_property]->(title) WHERE g.group = 'title'
	WITH n, c, title
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[r2:has_item]->(i:Item)-[:has_property]->(description) WHERE g.group = 'description'
	WITH n, c, title, description
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[r2:has_item]->(i:Item)-[:has_property]->(p) WHERE NOT (g.group = 'title' OR g.group ='description' OR g.group ='a')

	WITH n, c, title, description, Collect(i.itemID) AS items, Collect(g.group) AS groups, Collect(p) AS props

	RETURN n.href, c, title.content, description.content, Collect({items: items,groups: groups, p: props}) as itemlist

	ORDER BY c DESC
	SKIP $skip
	LIMIT $results_per_page";
	$result = $neo4j->sendCypherQuery($query);
	
	$out='';
	foreach ($result->getRecords() as $record) {
		$out.='<hr><div class="page"><a href="'.$record->value('n.href').'"><h2>'
		.($record->value('title.content') == ''? 'null' : $record->value('title.content'))
		.'</a> <-links- '.$record->value('c')
		.'</h2>'
		.'<h3>description</h3>'.($record->value('description.content') == ''? 'null' : $record->value('description.content'))
		.'<br>';
		
		
		$groups=[];$subGroup=[];$properties=[];
		if($record->value('itemlist') !== ''){
			foreach($record->value('itemlist') as $key => $item){
				if($key=='items'){					
			
					foreach($item as $k => $itemID){
						foreach( $itemID as $keys => $id){		
							if($k == 'groups'){					
								$groups[]= $id;								
							}
						}						
						
						foreach( $itemID as $keys => $id){		
							if($k == 'items'){
								$subGroup[$id][] =  $keys ;
							}
						}	
						
						foreach( $itemID as $keys => $id){		
							if($k == 'p'){
								foreach( $id as $content => $attr){		
									$properties[] = $attr;	
								}								
							}
						}						
					}			
				}			
			}
		}
		
		$new =[];				
		$subGroup = array_reverse($subGroup, true);		
		foreach($subGroup as $key=> $value){
			foreach($value as $key2){
				foreach($properties as $pkey => $property){
					if($key2 == $pkey){
						foreach($groups as $gkey => $gp){
							if($pkey == $gkey){
								$new[$gp][$key][$key2]=$property;
							}
						}					
					}					
				}
			}
		}				
		
		$new = array_reverse($new, true);			
		foreach($new as $group => $items){
			$out.='<div class="group">';
			$out.='<h3>'.$group.'</h3>';
			foreach($items as $item => $properties){
				$out.='<div class="item">';
				foreach($properties as $value){
					$out.='<div class="property">'.$value['property'].' : '.$value['content'].'</div>';
				}
				$out.='</div>';
				
			}
			$out.='</div>';
		}
		$out.='</div>';
	}

	
	
?>
<!doctype html>
<html>
<head>
<style>
.item {border: solid 1px grey;padding:1px 3px}
.page{border: 2px solid black;margin:5px;padding:5px;}
</style>
</head>

<body>

<form method="get">
Contains:<input type="text" style="width:250px;" name="search" value="<?php echo@$_GET['search']?>"><br>
<div class="center-align">
  <button type="submit">Search
  </button>
  </div>
</form>

<br>
<?php	
$total_page_count = ceil($count / $results_per_page);
$i = 0;
while(++$i <= $total_page_count){	
$style='black';
if(($page + 1) == $i){
	$style='green';
}
?>
<a style="border: 2px solid <?php echo $style; ?>; font-size:18px;margin: 3px;padding: 2px;" href="?page=<?php echo $i; ?>&search=<?php echo@$_GET['search']?>"><?php echo $i; ?></a>
<?php	
}

echo $count. ' Results';
?>


<hr>
<?php echo $out;?>
<hr>


<?php	

$i = 0;
while(++$i <= $total_page_count){	
$style='black';
if(($page + 1) == $i){
	$style='green';
}
?>
<a style="border: 2px solid <?php echo $style; ?>; font-size:18px;margin: 3px;padding: 2px;" href="?page=<?php echo $i; ?>&search=<?php echo@$_GET['search']?>"><?php echo $i; ?></a>
<?php	
}
?>
</body>
</html>
