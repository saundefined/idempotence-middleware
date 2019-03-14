<?php

namespace Saundefined\Middleware;

use Carbon\Carbon;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class IdempotenceMiddleware
{
    protected $pdo;

    private $tableName;

    /** @var \DateTimeInterface */
    private $expireAt = null;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->setExpireAt(Carbon::now()->addHour(24));
        $this->setTableName('idempotence_requests');
    }

    public function setExpireAt(\DateTimeInterface $expireAt)
    {
        $this->expireAt = $expireAt;
    }

    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $idempotenceKey = $request->getHeaderLine('Idempotence-Key');

        if (empty($idempotenceKey)) {
            return $response = $next($request, $response);
        }

        $response = $next($request, $response);

        if ($response instanceof ResponseInterface) {
            $requestParams = serialize($request->getQueryParams());

            $sql = $this->pdo->prepare('SELECT * FROM ' . $this->tableName . ' WHERE idempotence_key = :idempotence_key AND request = :request AND expire_at >= :expire_at');
            $sql->execute([
                'idempotence_key' => $idempotenceKey,
                'request' => $requestParams,
                'expire_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);
            $result = $sql->fetchObject();

            if ($result) {
                $response = $response->withStatus((int)$result->response_status);
                $response = $response->withHeader('Content-Type', 'application/json');
                $response->getBody()->rewind();
                $response->getBody()->write($result->response_body);

                return $response;
            }

            $responseStatus = $response->getStatusCode();
            $responseBody = $response->getBody()->__toString();

            $sql = $this->pdo->prepare('INSERT INTO ' . $this->tableName . ' (idempotence_key, expire_at, request, status, body) VALUES (:idempotence_key, :expire_at, :request, :status, :body)');
            $sql->execute([
                ':idempotence_key' => $idempotenceKey,
                ':expire_at' => $this->expireAt->format('Y-m-d H:i:s'),
                ':request' => $requestParams,
                ':status' => $responseStatus,
                ':body' => $responseBody
            ]);
        }

        return $response;
    }
}