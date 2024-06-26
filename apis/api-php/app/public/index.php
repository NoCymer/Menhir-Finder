<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\ContainerBuilder;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;

error_reporting (E_ERROR | E_WARNING | E_PARSE | E_NOTICE);


require __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../db/DBConnection.php';


//some config stuff
$containerBuilder = new ContainerBuilder();
$container = $containerBuilder->build();

$container->set('upload_directory', '/data/uploads');

AppFactory::setContainer($container);

//create
$app = AppFactory::create();

$app->add(function (Request $request, $handler) {
  $response = $handler->handle($request);
  return $response->withHeader('Access-Control-Allow-Origin', '*')
                  ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Origin, Accept')
                  ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                  ->withHeader('Access-Control-Allow-Credentials', 'true');
});

// Route to handle OPTIONS preflight requests
$app->options('/{routes:.+}', function (Request $request, Response $response, $args) {
  return $response->withHeader('Access-Control-Allow-Origin', '*')
                  ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Origin, Accept')
                  ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                  ->withHeader('Access-Control-Allow-Credentials', 'true')
                  ->withStatus(204);
});


$app->get('/', function (Request $request, Response $response, $args) {
  $response->getBody()->write("Welcome in Toutatixe !");
  return $response;
});


$app->get('/tests', function (Request $request, Response $response, $args) {
  $jsonresult = array();

  //try to connect to nodejs api
  try {
    $client = new GuzzleHttp\Client(['base_uri' => 'http://node:3000/']);
    $resfromnodejs = $client->request('GET', 'api/');
    if ($resfromnodejs->getStatusCode()==201 || $resfromnodejs->getStatusCode()==200)
    {
      $jsonresult["connection to nodejs : "] = "ok : ". json_decode($resfromnodejs->getBody());
    }  
    else{
      $jsonresult["connection to nodejs : "] = "err : ".$resfromnodejs->getStatusCode();
    }
  } catch (GuzzleHttp\Exception\ClientException $e) {
    $jsonresult["connection to nodejs : "] = "err : ".$e->getMessage();
  } catch (GuzzleHttp\Exception\ClientException $e) {
    $jsonresult["connection to nodejs : "] = "err : ".$e->getMessage();
  }


  //prepar query to check if user exists
  $sql = "SELECT * FROM users";
 
  try {
      //connect to DB
      $dbconn = new DB\DBConnection();
      $db = $dbconn->connect();
          
      // https://www.php.net/manual/en/pdo.prepare.php
      $stmt = $db->prepare( $sql );

      // query
      $stmt->execute();

      // get found user(s) array (should contain 1 entry)
      $foundusers = $stmt->fetchAll( );

      $db = null; // clear db object

      if (count($foundusers)>0)
      {
        $jsonresult["DB Connection : "] = "ok : ". count($foundusers) . "user(s) found";

      }
      else
      {
        $jsonresult["DB Connection : "] = "ok : ". count($foundusers) . "user(s) found";

      }
  }
  catch( PDOException $e ) {
    $jsonresult["DB Connection : "] = "err : ". $e->getMessage();
  }
  

  
  $response->getBody()->write(json_encode($jsonresult));
  
  return $response;
});

# include Auth route
require __DIR__ . '/../apiroutes/auth.php';

# include Guesses routes
require __DIR__ . '/../apiroutes/public_guesses.php';

# include Guesses routes
require __DIR__ . '/../apiroutes/private_guesses.php';

/**
 * Catch-all route to serve a 404 Not Found page if none of the routes match
 * NOTE: make sure this route is defined last
 */
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
  throw new Slim\Exception\HttpNotFoundException($request);
});

$app->run();