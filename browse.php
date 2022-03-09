<?php error_reporting(0);
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
/* old
use GraphAware\Neo4j\Client\ClientBuilder;
$neo4j = ClientBuilder::create()->addConnection('default', 'http://neo4j:admin@localhost:7474')->build(); // Example for HTTP connection configuration (port is optional)	
*/
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
	$neo4j = ClientBuilder::create()
    ->withDriver('bolt', 'bolt://superuser:admin@localhost:7687') // creates a bolt driver
   //  ->withDriver('https', 'https://localhost:7474', Authenticate::basic('superuser', 'admin')) // creates an http driver
   // ->withDriver('neo4j', 'neo4j://neo4j.test.com?database=my-database', Authenticate::oidc('token')) // creates an auto routed driver with an OpenID Connect token
  ->withDefaultDriver('bolt')
    ->build();	


$results_per_page=5;

$page = 1;		
if(!empty($_GET['page'])) {
	$page = $_GET['page'];
}
	
$page--;
$skip = $page * $results_per_page;


$search = '';
if(!empty($_GET['search'])) {
	$search = $_GET['search'];
}
	
	
	$query = '	
	MATCH (n:Url)-[:has_group]->(g:Group)-[:has_item]->(i:Item)-[:has_property]->(p)
	WHERE ((p.content =~ $search AND g.group = "title")
	OR (p.content =~ $search AND g.group = "description")
	OR (p.content =~ $search AND g.group = "h1s")) 
	AND NOT EXISTS(n.is404) AND n.type = "internal"
	RETURN count(DISTINCT n)';
	
	$results1 = $neo4j->run($query,["search"=>"(?i).*$search.*"]);
		
foreach ($results1 as $result1) {
		$count = $result1->get('count(DISTINCT n)');
	}
		
	$query = '
	MATCH (n:Url)-[:has_group]->(g:Group)-[:has_item]->(i:Item)-[:has_property]->(p)
	WHERE ((p.content =~ $search AND g.group = "title")
	OR (p.content =~ $search AND g.group = "description")
	OR (p.content =~ $search AND g.group = "h1s")) 
	AND NOT EXISTS(n.is404) AND n.type = "internal"
	
	WITH DISTINCT n, 
		
	SUM(CASE WHEN (p.content =~ $search AND g.group = "title") THEN 2 ELSE 0 END ) AS titlecount, 
	SUM(CASE WHEN (p.content =~ $search AND g.group = "description") THEN 1 ELSE 0 END ) AS desccount,
	SUM(CASE WHEN (p.content =~ $search AND g.group = "h1s") THEN 1 ELSE 0 END ) AS h1scount

	
	OPTIONAL MATCH (linkednodes:Url)-[r]->(n)
	WITH DISTINCT n, count(DISTINCT linkednodes) as ln, count(DISTINCT r) as lc,(CASE WHEN titlecount > 2 THEN 2 ELSE titlecount END) + (CASE WHEN desccount > 1 THEN 1 ELSE desccount END) + (CASE WHEN h1scount > 1 THEN 1 ELSE h1scount END) AS rank 
	OPTIONAL MATCH (linkstointernternal:Url {type: "internal"})<-[]-(n)
	WITH DISTINCT n, ln, lc, count(DISTINCT linkstointernternal) as lti, rank
	OPTIONAL MATCH (linkstoexternternal:Url {type: "external"})<-[]-(n)
	WITH DISTINCT n, ln, lc, lti, count(DISTINCT linkstoexternternal) as lte, rank
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[:has_item]->(i:Item)-[:has_property]->(title) WHERE g.group = "title"
	WITH DISTINCT n, rank, ln, lti, lte, lc, title
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[:has_item]->(i:Item)-[:has_property]->(description) WHERE g.group = "description"
	WITH  DISTINCT n, rank, ln, lti, lte, lc, title, description
	OPTIONAL MATCH (n)-[:has_group]->(g:Group)-[:has_item]->(i:Item)-[:has_property]->(p) WHERE NOT (g.group = "title" OR g.group ="description" OR g.group ="a")
	WITH DISTINCT n,rank, ln, lti, lte, lc, title, description, Collect(i.itemID) AS items, Collect(g.group) AS groups, Collect(p) AS props
		
	RETURN n.href, n.pr, rank, ln, lti, lte, lc, title.content, description.content, Collect({items: items,groups: groups, p: props}) as itemlist
	ORDER BY rank DESC, n.pr DESC
	SKIP $skip
	LIMIT $rpp';
		
	$results = $neo4j->run($query,["search"=>"(?i).*$search.*","skip"=>$skip,"rpp"=>$results_per_page]);//'(?i).*
	
	$out='';
	foreach ($results as $result) {
		$out.='<hr><div class="page"><h2><a href="'.$result->get('n.href').'">'
		.($result->get('title.content') == ''? 'null' : $result->get('title.content'))
		.'</a></h2>' 
		.'<h4 id="stats">Page Rank: '.$result->get('n.pr').' - Unique Page Links: '.$result->get('ln').' - Total Links: '.$result->get('lc').' - Links to internal: '.$result->get('lti').' - Links to external: '.$result->get('lte').'  - T2+D1+H1s1 Score: '.$result->get('rank')
		.'</h4>'
		.'<h3>description</h3>'.($result->get('description.content') == ''? 'null' : $result->get('description.content'))
		.'<br>';
		
		
		$groups=[];$subGroup=[];$props=[];
		if($result->get('itemlist') !== ''){
			foreach($result->get('itemlist') as $key => $item){
				
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
							
									$props[] = $attr;	
									
								}								
							}
						}						
					}			
				}			
			}
		}
		
		/* added to restore $properties to prior outcome that was had with previous database adapter */
		$properties=[];
			foreach( $props as $att){		
				if(! IS_numeric( $att))	{
					
				$temp=[];
					foreach( $att as $blah){						
						if($blah != 'Property' && $blah != ''){								
							$temp[] =$blah;//echo '<br>'.						
						}						
					}	
					if($temp[0] != ''){		
						$properties[]=['property'=>$temp[0],'content'=>$temp[1]];
					}
			
				}		
			}
		
	/*	
		echo '<pre>';
		var_dump($properties);
		echo '</pre>';
		
			echo '<pre>--';
		var_dump($subGroup);
		echo '</pre>';
		
		
	echo '<hr>';
*/		
	
		
		
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
	
		
		//$new = array_reverse($new, true);			
		
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
h4#stats{color:grey;}
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
