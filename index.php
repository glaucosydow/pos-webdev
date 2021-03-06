<?php
use Zend\Expressive\AppFactory;

$loader = require 'vendor/autoload.php';
$loader->add('RestBeer', __DIR__.'/src');

$app = AppFactory::create();

$app->get('/', function ($request, $response, $next) {
    $session = $request->getAttribute(\PSR7Session\Http\SessionMiddleware::SESSION_ATTRIBUTE);
    $session->set('counter', $session->get('counter', 0) + 1);
    $response->getBody()->write('Hello, world!' . $session->get('counter'));
    return $response;
});

$beers = array(
    'brands' => array('Heineken', 'Guinness', 'Skol', 'Colorado'),
    'styles' => array('Pilsen' , 'Stout')
);

$app->get('/brands', function ($request, $response, $next) use ($beers) {
    // $response->getBody()->write(implode(',', $beers['brands']));
    $response->getBody()->write(serialize($beers['brands']));

    return $next($request, $response);
});

$app->get('/styles', function ($request, $response, $next) use ($beers) {
    // $response->getBody()->write(implode(',', $beers['styles']));
    $response->getBody()->write(serialize($beers['styles']));

    return $next($request, $response);
});

$app->get('/beer/{id}', function ($request, $response, $next) use ($beers) {
    $id = $request->getAttribute('id');
    if (!isset($beers['brands'][$id])) {
        return $response->withStatus(404);
    }

    $response->getBody()->write($beers['brands'][$id]);

    return $response;
});

$db = new PDO('sqlite:beers.db');
$app->post('/beer', function ($request, $response, $next) use ($db) {
    $db->exec(
        "create table if not exists 
beer (id INTEGER PRIMARY KEY AUTOINCREMENT, name text not null, style text not null)"
    );

    $data = $request->getParsedBody();
    //@TODO: clean form data before insert into the database ;)
    $stmt = $db->prepare('insert into beer (name, style) values (:name, :style)');
    $stmt->bindParam(':name',$data['name']);
    $stmt->bindParam(':style', $data['style']);
    $stmt->execute();
    $data['id'] = $db->lastInsertId();

    $response->getBody()->write($data['id']);

    return $response->withStatus(201);
});

$session = new \RestBeer\Session();
$app->pipe($session->get());
$app->pipe(new \RestBeer\Auth());
$app->pipeRoutingMiddleware();
$app->pipeDispatchMiddleware();
$app->pipe(new \RestBeer\Format());
$app->run();
