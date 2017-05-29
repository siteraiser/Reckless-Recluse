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
