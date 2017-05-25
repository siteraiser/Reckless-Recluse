# Reckless-Recluse
A powerful php crawler designed to dig up site problems. 

Requirements:
mysql, neo4j 3 and php

You will have to create 3 mysql tables as is... 
CREATE TABLE `crawl`.`urls_captured` ( `id` INT(10) NOT NULL AUTO_INCREMENT , `url` VARCHAR(255) NOT NULL , PRIMARY KEY (`id`), UNIQUE (`url`)) ENGINE = InnoDB;
CREATE TABLE `crawl`.`to_crawl` ( `id` INT(10) NOT NULL AUTO_INCREMENT , `url` VARCHAR(255) NOT NULL , PRIMARY KEY (`id`), UNIQUE (`url`)) ENGINE = InnoDB;
CREATE TABLE `crawl`.`crawled` ( `id` INT(10) NOT NULL AUTO_INCREMENT , `url` VARCHAR(255) NOT NULL , PRIMARY KEY (`id`), UNIQUE (`url`)) ENGINE = InnoDB;

And just one Neo4j db. But you'll have to add a vendor folder with the php cypher extensions. Which can be found here: https://github.com/graphaware/neo4j-php-client

After it's up and running, you can use xpath queries to choose what content to save into the database, also what will appear in the reports. 
Example of how to change crawl behavior to only crawl nav links and links inside of a main element (the 'a' group is used to crawl urls, other groups will only show up in the page reports):

$data['a'] = ['main'=>['.//a'=>['href']],'nav'=>['.//a'=>['href']]]; 
