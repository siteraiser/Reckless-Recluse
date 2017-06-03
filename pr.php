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


	$query = "	
	MATCH (n: Url {type: 'internal'})<-[r:references]-(lto: Url {type: 'internal'})-[rs:references]->(:Url)
	WHERE NOT (lto)-[]->(lto)
	WITH n,lto, (1 / toFloat(count(distinct rs))) * toFloat(count(distinct r)) AS pr
	WITH n, SUM(pr) AS r
	SET n.pr = r";
	$result1 = $neo4j->run($query);
		
		
	
?>
<!doctype html>
<html>
<head>
<style>

</style>
</head>

<body>
<h3>Page Rank Generated</h3>

</body>
</html>
