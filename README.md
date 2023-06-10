# Reckless-Recluse-V1.2
Updated for new Laudis Neo4J connector

A powerful php crawler designed to dig up site problems. 

Requirements:
mysql, neo4j 4+ and php7,8

You will have to create 3 mysql tables as shown below (recommended collation: utf8mb4_unicode_ci). 

(db name:crawl)... 

CREATE TABLE `crawl`.`urls_captured` ( `id` INT(10) NOT NULL AUTO_INCREMENT , `url` VARCHAR(255) NOT NULL , PRIMARY KEY (`id`), UNIQUE (`url`)) ENGINE = InnoDB;
CREATE TABLE `crawl`.`to_crawl` ( `id` INT(10) NOT NULL AUTO_INCREMENT , `url` VARCHAR(255) NOT NULL , PRIMARY KEY (`id`), UNIQUE (`url`)) ENGINE = InnoDB;
CREATE TABLE `crawl`.`crawled` ( `id` INT(10) NOT NULL AUTO_INCREMENT , `url` VARCHAR(255) NOT NULL , PRIMARY KEY (`id`), UNIQUE (`url`)) ENGINE = InnoDB;

And just one Neo4j db, set user/pass details in connection area. You'll have to add a vendor folder with the php cypher extensions. Which can be found here: https://github.com/neo4j-php/neo4j-php-client

After it's up and running, you can use xpath queries to choose what content to save into the database, also what will appear in the reports. 
Below is an example of how to change crawl behavior to only crawl nav links and links inside of a main element (the 'a' group is used to crawl urls, other groups will only show up in the page reports). The other gathered info will also be saved to the graph. 

$data['a'] = ['main'=>['.//a'=>['href']],'nav'=>['.//a'=>['href']]]; 

And if you need to grab a few values in different forms, you could add in some more custom functionality like the included innertext function. Here's how to get the link, text and rel attributes from any 'a' elements in the main section if there is one (already included in the current script, innertext will get the text from any node): 

$data['mainlinks'] = ['main'=>['.//a'=>['href','innertext','rel']]];

---
Depending on website setup, you may want to change the useragent from the default mobile ua.
To enable external url check, change setting to crawlLinks->capture = 'all', '' is default.

After succesfully crawling a website, page rank will be generated and you can then head to the browse.php file to search and see what the rank for each page is. 
