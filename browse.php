<?php
/*	Copyright © 2017 
	
	This file is part of Reckless Recluse.
    Reckless Recluse is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
    Reckless Recluse is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with Reckless Recluse.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once $_SERVER['DOCUMENT_ROOT'].'/'.'vendor/autoload.php';
use GraphAware\Neo4j\Client\ClientBuilder;


$neo4j = ClientBuilder::create()->addConnection('default', 'http://neo4j:admin@localhost:7474')->build(); // Example for HTTP connection configuration (port is optional)	

$results_per_page=5;

$page = 1;		
if(!empty($_GET['page'])) {
	$page = $_GET['page'];
}
	
$page--;
$skip = $page * $results_per_page;


$name = '';
if(!empty($_GET['search'])) {
	$name = $_GET['search'];
}
	
	
	$query = "	
	MATCH (n:Url)-[:has_group]->(g:Group)-[:has_item]->(i:Item)-[:has_property]->(p)
	WHERE ((p.content =~ {name} AND g.group = 'title')
	OR (p.content =~ {name} AND g.group = 'description')) 
	AND NOT EXISTS(n.is404) AND n.type = 'internal'
	RETURN count(DISTINCT n)";
	
	$result1 = $neo4j->run($query,["name"=>"(?i).*$name.*"]);
		
	foreach ($result1->getRecords() as $record1) {
		$count = $record1->value('count(DISTINCT n)');
	}
		

	$query = "
	MATCH (n:Url)-[:has_group]->(g:Group)-[:has_item]->(i:Item)-[:has_property]->(p)
	WHERE ((p.content =~ {name} AND g.group = 'title')
	OR (p.content =~ {name} AND g.group = 'description')) 
	AND NOT EXISTS(n.is404) AND n.type = 'internal'
	

	WITH DISTINCT n, 
	SUM(CASE WHEN (p.content =~ {name} AND g.group = 'title') THEN 2 ELSE 0 END ) AS titlecount, 
    SUM(CASE WHEN (p.content =~ {name} AND g.group = 'description') THEN 1 ELSE 0 END ) AS desccount
	
	MATCH ()-[r]->(n)
	WITH n, count(DISTINCT r) as c, titlecount + desccount AS rank
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[:has_item]->(i:Item)-[:has_property]->(title) WHERE g.group = 'title'
	WITH n, c, title, rank
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[:has_item]->(i:Item)-[:has_property]->(description) WHERE g.group = 'description'
	WITH n, c, title, description, rank
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[:has_item]->(i:Item)-[:has_property]->(p) WHERE NOT (g.group = 'title' OR g.group ='description' OR g.group ='a')
	WITH n, c, title, description, rank, Collect(i.itemID) AS items, Collect(g.group) AS groups, Collect(p) AS props
	

	
	RETURN rank, n.href, c, title.content, description.content, Collect({items: items,groups: groups, p: props}) as itemlist
	ORDER BY rank DESC, c DESC
	SKIP {skip}
	LIMIT {rpp}";
		
		

	$result = $neo4j->run($query,["name"=>"(?i).*$name.*","skip"=>$skip,"rpp"=>$results_per_page]);//'(?i).*
	
	$out='';
	foreach ($result->getRecords() as $record) {
		$out.='<hr><div class="page"><a href="'.$record->value('n.href').'"><h2>'
		.($record->value('title.content') == ''? 'null' : $record->value('title.content'))
		.'</a> <-Links- '.$record->value('c').'  - T2+D1 Score: '.$record->value('rank')
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
<a style="border: 2px solid <?php echo $style; ?>; font-size:18px;margin: 3px;padding: 2px;" href="?page=<?php echo $i; ?>&amp;search=<?php echo@$_GET['search']?>"><?php echo $i; ?></a>
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
<a style="border: 2px solid <?php echo $style; ?>; font-size:18px;margin: 3px;padding: 2px;" href="?page=<?php echo $i; ?>&amp;search=<?php echo@$_GET['search']?>"><?php echo $i; ?></a>
<?php	
}
?>
</body>
</html>
