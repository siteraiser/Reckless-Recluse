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


/* old

use GraphAware\Neo4j\Client\ClientBuilder;


$neo4j = ClientBuilder::create()->addConnection('default', 'http://neo4j:admin@localhost:7474')->setDefaultTimeout(30)->build(); // Up the max ex. timeout. Example for HTTP connection configuration (port is optional)	

*/

	$neo4j = ClientBuilder::create()
    ->withDriver('bolt', 'bolt://superuser:admin@localhost:7687') // creates a bolt driver
   //  ->withDriver('https', 'https://localhost:7474', Authenticate::basic('superuser', 'admin')) // creates an http driver
   // ->withDriver('neo4j', 'neo4j://neo4j.test.com?database=my-database', Authenticate::oidc('token')) // creates an auto routed driver with an OpenID Connect token
  ->withDefaultDriver('bolt')
    ->build();	  

	//set nofollows on relationships
	$query = "
	MATCH (n:Url)<-[r:references]-(lto: Url {type: 'internal'})-[:has_group]->(g:Group {group: 'mainlinks'})-[:has_item]->()-[:has_property]->(links) WHERE ((links.property = 'rel') AND (links.content = 'nofollow') )	
	WITH n, lto, r, links, collect(DISTINCT links.content) AS linkCollection2
	MATCH (n)<-[r:references]-(lto) WHERE n.href IN linkCollection2
	SET r.rel = 'nofollow'
	return r
	";
	$result1 = $neo4j->run($query);
	//Calculate Page Rank
	$query = "	
	MATCH (n: Url {type: 'internal'})<-[r:references]-(lto: Url {type: 'internal'})-[rs:references]->(:Url)
	WHERE NOT rs.rel = 'nofollow'//NOT (lto)-[]->(lto) AND 
	WITH n,lto, (1 / toFloat(count(distinct rs))) * toFloat(count(distinct r)) AS pr
	WITH n, SUM(pr) AS r
	SET n.pr = r";
	$result1 = $neo4j->run($query);
	
	/* If the site has main elements, you can base page rank on this instead or in addition (browse.php would likely need adjusting)

	
	MATCH (n: Url {type: 'internal'})<-[r:references]-(lto: Url {type: 'internal'})-[:has_group]->(g:Group {group: 'mainlinks'})-[:has_item]->(:Item)-[:has_property]->(links) WHERE ((links.property = 'href') AND NOT r.rel = 'nofollow')	
	WITH n, lto,r, links, collect(DISTINCT links.content) AS linkCollection2	
	MATCH (n:Url)<-[:references {rel:''}]-(lto: Url {type: 'internal'})-[:has_group]->(:Group {group: 'mainlinks'})-[:has_item]->(:Item)-[:has_property]->(links2) WHERE (links2.property = 'href') AND n.href IN linkCollection2
	WITH DISTINCT n,  toFloat(count(linkCollection2)) AS l2c, toFloat(count(DISTINCT r)) AS all
	WITH DISTINCT n, CASE WHEN l2c = 0 THEN 0 ELSE 1 / l2c END * all AS pr	
	WITH n, SUM(pr) AS r
	SET n.pr = r
	
	//Or some other less accurate versions

	
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

