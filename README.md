# Idempotence middleware

PSR-7 middleware that helps prevent duplicate requests.

## Installing

````bash
composer require saundefined/idempotence-middleware
````

## Create SQL Table

````sql

CREATE TABLE idempotence_requests (
  id				INT				NOT NULL AUTO_INCREMENT,
  idempotence_key	VARCHAR(40)		NOT NULL,
  expire_at			TIMESTAMP		NOT NULL,
  request_params	LONGTEXT    	NULL,
  response_status	INT(3)	    	NOT NULL,
  response_body		LONGTEXT    	NULL,

  PRIMARY KEY (id)
);

````

## Example

````php

$pdo = new \PDO('mysql:host=localhost;dbname=database, 'login', 'password');
    
$idempotence = new \Saundefined\Middleware\IdempotenceMiddleware($pdo);

// Optional – default is 24 hours
$idempotence->setExpireAt(Carbon::now()->addHours(10));

// Optional - default table name is idempotence_requests
$idempotence->setTableName('idempotence_requests');

// Your PSR-7 app
$app->get('/test', function (Request $request, Response $response, array $args) {

    return $response->withJson([]);
})->add($idempotence);
````