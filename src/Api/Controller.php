<?php

namespace Centamiv\Vektor\Api;

use Centamiv\Vektor\Core\Config;
use Centamiv\Vektor\Services\Indexer;
use Centamiv\Vektor\Services\Searcher;
use Exception;

class Controller
{
    /**
     * Handles the incoming HTTP request.
     * Routes to appropriate handler based on URI.
     */
    public function handleRequest(): void
    {
        header('Content-Type: application/json');

        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $uri = $_SERVER['REQUEST_URI'];

            if ($method === 'GET' && str_contains($uri, '/up')) {
                $this->handleUp();
                return;
            }

            $this->authenticate();

            match (true) {
                $method === 'POST' && str_contains($uri, '/insert') => $this->handleInsert(),
                $method === 'POST' && str_contains($uri, '/delete') => $this->handleDelete(),
                $method === 'POST' && str_contains($uri, '/search') => $this->handleSearch(),
                $method === 'POST' && str_contains($uri, '/optimize') => $this->handleOptimize(),
                $method === 'GET' && str_contains($uri, '/info') => $this->handleInfo(),
                default => $this->sendNotFound(),
            };
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function sendNotFound(): void
    {
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
    }

    private function handleInsert()
    {
        $input = $this->getJsonInput();

        // Validate ID
        if (!isset($input['id']) || !is_string($input['id'])) {
            throw new Exception("Missing or invalid 'id': must be a string.");
        }
        if (strlen($input['id']) > 36 || strlen($input['id']) < 1) {
            throw new Exception("Invalid 'id': length must be between 1 and 36 characters.");
        }

        // Validate Vector
        if (!isset($input['vector'])) {
            throw new Exception("Missing 'vector' in payload.");
        }
        $this->validateVector($input['vector']);

        $indexer = new Indexer();
        $indexer->insert($input['id'], $input['vector']);

        echo json_encode(['status' => 'success', 'id' => $input['id']]);
    }

    private function handleInfo()
    {
        $indexer = new Indexer();
        $stats = $indexer->getStats();
        echo json_encode($stats);
    }

    private function handleDelete()
    {
        $input = $this->getJsonInput();

        if (!isset($input['id']) || !is_string($input['id'])) {
            throw new Exception("Missing or invalid 'id': must be a string.");
        }

        $indexer = new Indexer();
        $deleted = $indexer->delete($input['id']);

        if ($deleted) {
            echo json_encode(['status' => 'success', 'message' => "Document '{$input['id']}' deleted."]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => "Document '{$input['id']}' not found."]);
        }
    }

    private function handleSearch()
    {
        $input = $this->getJsonInput();

        // Validate Vector
        if (!isset($input['vector'])) {
            throw new Exception("Missing 'vector' in payload.");
        }
        $this->validateVector($input['vector']);

        // Validate k
        $k = $input['k'] ?? 10;
        if (!is_int($k) || $k < 1) {
            throw new Exception("Invalid 'k': must be a positive integer.");
        }

        $searcher = new Searcher();
        $includeVector = \filter_var($input['include_vector'] ?? false, \FILTER_VALIDATE_BOOL);
        $results = $searcher->search($input['vector'], $k, $includeVector);

        echo json_encode(['results' => $results]);
    }

    protected function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        try {
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            throw new Exception("Invalid JSON payload: " . $e->getMessage());
        }

        if (!is_array($input)) {
            throw new Exception("Invalid JSON payload: expected a JSON object.");
        }
        return $input;
    }

    private function validateVector(mixed $vector): void
    {
        if (!is_array($vector)) {
            throw new Exception("Invalid 'vector': must be an array.");
        }
        if (count($vector) !== \Centamiv\Vektor\Core\Config::getDimensions()) {
            throw new Exception("Invalid 'vector': must have exactly " . \Centamiv\Vektor\Core\Config::getDimensions() . " dimensions.");
        }
        foreach ($vector as $val) {
            if (!is_numeric($val)) {
                throw new Exception("Invalid 'vector': all elements must be numeric.");
            }
        }
    }

    private function handleOptimize()
    {
        $optimizer = new \Centamiv\Vektor\Services\Optimizer();
        $optimizer->run();

        echo json_encode(['status' => 'success', 'message' => 'Database optimized successfully.']);
    }

    private function handleUp()
    {
        echo json_encode(['status' => 'up']);
    }

    private function authenticate(): void
    {
        $token = Config::getApiToken();

        // If no token is configured in .env, Auth is disabled (Free access)
        if (empty($token)) {
            return;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        // Handle "Bearer <token>"
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $providedToken = $matches[1];
            if ($providedToken === $token) {
                return;
            }
        }

        // If we fall through, Auth failed
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}
