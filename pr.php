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
	
	/* If the site has main elements, you can base page rank on this instead 
	//set nofollows on relationships
	$query = "
		MATCH (n: Url {type: 'internal'})<-[r:references]-(lto: Url {type: 'internal'})-[:has_group]->(g:Group {group: 'mainlinks'})-[:has_item]->()-[:has_property]->(links) WHERE ((links.property = 'href') AND NOT r.rel = 'nofollow') AND NOT (lto)-[]->(lto)
	
	WITH n, lto, links, collect(DISTINCT links.content) AS linkCollection2
	
	MATCH (n)<-[r:references]-(lto) WHERE n.href IN linkCollection2
	WITH n, lto, (1 / toFloat(count(linkCollection2))) * toFloat(count(distinct n)) AS pr
	WITH n, SUM(pr) AS r
	SET n.pr = r
	
	";
	$result1 = $neo4j->run($query);
	
	//Do Page Rank
	$query = "
	MATCH (n: Url {type: 'internal'})<-[r:references]-(lto: Url {type: 'internal'})-[:has_group]->(g:Group {group: 'mainlinks'})-[:has_item]->()-[:has_property]->(links) WHERE links.property = 'href' AND NOT (r.rel = 'nofollow') AND NOT (lto)-[]->(lto)
	WITH n, lto, (1 / toFloat(count(distinct links))) * toFloat(count(distinct r)) AS pr
	WITH n, SUM(pr) AS r
	SET n.pr = r
	";
	$result1 = $neo4j->run($query);
	*/
?>

<h3>Page Rank Generated</h3>

