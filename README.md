# Vektor - Native PHP Vector Database

**Vektor** is a high-performance, purely file-based, embedded **Vector Database** written entirely in native PHP. It is designed for **Zero-RAM Overhead**, meaning it does not require loading your entire dataset into memory to function.

**Each Vektor instance operates as a standalone database**, with data stored by default in the `/data` directory.

Instead of memory-heavy indexes, Vektor utilizes strict binary file layouts and optimized disk-seeking strategies to perform **Approximate Nearest Neighbor (ANN)** searches using the **HNSW (Hierarchical Navigable Small World)** algorithm.

---

## Features

- **Pure PHP**: No external dependencies or C extentions required. Runs on any standard PHP 8.2+ environment.
- **Zero-RAM Overhead**: Data is read directly from disk. Memory usage is constant regardless of dataset size.
- **HNSW Algorithm**: Efficient graph-based index for fast approximate nearest neighbor search.
- **Binary Storage**: Compact binary file formats for Vectors, Graph connections, and Metadata.
- **Embedded or Server**: Use it directly in your PHP code or run it as a standalone HTTP API server.
- **Thread-Safety**: Implements file locking (flock) to safely handle concurrent reads and writes.
- **Cosine Similarity**: Optimized distance metric for high-dimensional embeddings (configurable, default 1536).

---

## Requirements

- **PHP**: 8.2 or higher
- **Composer**: For dependency management

---

## Installation

### 1. Installation via Composer

To use Vektor in your existing PHP project:

```bash
composer require centamiv/vektor
```

### 2. Standalone Installation

To run Vektor as a standalone API server:

```bash
git clone https://github.com/centamiv/vektor.git
cd vektor
composer install --no-dev
```

Ensure the `data/` directory is writable by your web server or script user:

```bash
mkdir -p data
chmod -R 775 data
```

---

## Configuration

Vektor uses a `.env` file for configuration when running as a server.

1.  Copy the example environment file:
    ```bash
    cp .env.example .env
    ```

2.  Open `.env` and configure your API Token:
    ```ini
    # .env
    VEKTOR_API_TOKEN=your_secure_random_string_here
    VEKTOR_DIMENSIONS=1536
    ```

-   **VEKTOR_API_TOKEN**: If set, all API requests (except `/up`) must include this token in the `Authorization` header. If left empty, the API is open to the public.
-   **VEKTOR_DIMENSIONS**: Set the dimension of your vectors (default: 1536). IMPORTANT: Changing this requires a fresh database (delete data/ dir).

---

## Usage

Vektor is designed for flexibility, allowing you to either integrate it directly into your PHP projects as a library or deploy it as a standalone REST API server.

---

### Usage: HTTP API Server

Vektor includes a built-in Controller to run as a REST API. You can serve this using Apache, Nginx, or the PHP built-in server.

#### Starting the Server

For testing/development:
```bash
# Serves the public/ directory on port 8000
php -S 0.0.0.0:8000 -t public
```

#### Authentication

If `VEKTOR_API_TOKEN` is set in your `.env`, you must include the header in all requests:

```
Authorization: Bearer <your-token>
```

#### API Endpoints

##### `GET /up`
Health check endpoint.
- **Auth Required**: No
- **Response**: 
```json
{
  "status": "up"
}
```

##### `GET /info`
Returns database statistics.
- **Response**:
```json
{
  "storage": {
    "vector_file_bytes": 1048576,
    "graph_file_bytes": 524288,
    "meta_file_bytes": 2048,
    "payload_file_bytes": 4096
  },
  "records": {
    "vectors_total": 150,
    "graph_nodes": 150
  },
  "config": {
    "dimension": 1536,
    "max_levels": 4
  }
}
```

##### `POST /insert`
Insert a vector.
- **Body**:
```json
{
  "id": "my-doc-id",
  "vector": [0.1, 0.2, 0.3, ...],
  "metadata": {
    "source": "docs/intro.md",
    "chunk": 3
  }
}
```
- **Response**:
```json
{
  "status": "success",
  "id": "my-doc-id"
}
```

##### `POST /search`
Search for nearest neighbors.
- **Body**:
```json
{
  "vector": [0.1, 0.2, 0.3, ...],
  "k": 5
}
```
- **Response**:
```json
{
  "results": [
    { "id": "my-doc-id", "distance": 0.95 },
    { "id": "another-id", "distance": 0.88 }
  ]
}
```

Optionally pass `"include_vector": true` to also get vector data of similar documents.
Optionally pass `"include_metadata": true` to also get metadata stored with the document.

- **Body**:
```json
{
  "vector": [0.1, 0.2, 0.3, ...],
  "include_vector": true,
  "include_metadata": true,
  "k": 5
}
```
- **Response**:
```json
{
  "results": [
    { "id": "my-doc-id", "distance": 0.95, "vector": [0.5, 1.0, 0.3, ...], "metadata": { "source": "docs/intro.md", "chunk": 3 } },
    { "id": "another-id", "distance": 0.88, "vector": [0.5, 1.1, 0.3, ...], "metadata": { "source": "docs/faq.md", "chunk": 1 } }
  ]
}
```


##### `POST /delete`
Delete a vector.
- **Body**:
```json
{
  "id": "my-doc-id"
}
```
- **Response**:
```json
{
  "status": "success",
  "message": "..."
}
```

##### `POST /optimize`
Trigger database optimization.
- **Response**:
```json
{
  "status": "success",
  "message": "..."
}
```

---

### Usage: Embedded Library

You can use Vektor directly in your PHP scripts without running an HTTP server. This is the fastest way to interact with the database.

#### Configuration

By default, Vektor stores data in the `data/` directory relative to the package root. You can change this path using the `Config` class:

```php
use Centamiv\Vektor\Core\Config;

Config::setDataDir(__DIR__ . '/my_custom_data_dir');
```

You can also set the vector dimensions (default 1536):

```php
Config::setDimensions(768);
// Note: This must be called BEFORE initializing Indexer/Searcher
```

#### Initialization

```php
use Centamiv\Vektor\Services\Indexer;
use Centamiv\Vektor\Services\Searcher;
use Centamiv\Vektor\Services\Optimizer;

// The Indexer handles writing (Insert, Delete)
$indexer = new Indexer();

// The Searcher handles reading (Search)
$searcher = new Searcher();
```

#### 1. Inserting Vectors

Vectors must be **1536-dimensional arrays** of floats.

```php
$id = "doc-123"; // String ID (max 36 chars)
$vector = [0.0123, -0.5231, ...]; // Array of 1536 floats

// Insert (or update if ID exists - NOTE: Updates are essentially Appends with pointer updates)
$metadata = ['source' => 'docs/intro.md', 'chunk' => 3];
$indexer->insert($id, $vector, $metadata);
```

#### 2. Searching

Find the `k` nearest neighbors to a query vector.

```php
$queryVector = [0.0123, ...];
$k = 5; // Number of results

$results = $searcher->search($queryVector, $k, includeMetadata: true);

// Output:
// [
//   ['id' => 'doc-123', 'score' => 0.9823, 'metadata' => ['source' => 'docs/intro.md', 'chunk' => 3]],
//   ['id' => 'doc-456', 'score' => 0.8912, 'metadata' => ['source' => 'docs/faq.md', 'chunk' => 1]],
//   ...
// ]
```

#### 3. Deleting Results

Deletes a document by its ID. This performs a "soft delete" in the vector file and updates the metadata mapping.

```php
$success = $indexer->delete("doc-123");

if ($success) {
    echo "Document deleted.";
} else {
    echo "Document not found.";
}
```

#### 4. Getting Statistics

Retrieve current database stats, including file sizes and node counts.

```php
$stats = $indexer->getStats();
print_r($stats);
```

#### 5. Optimizing (Vacuum)

Since deletions are "soft", the file size can grow over time. Run the optimizer to rebuild the index and reclaim space. **Note**: This is a blocking operation.

```php
$optimizer = new Optimizer();
$optimizer->run();
```

---

## Database File Structure

Vektor achieves its performance and low memory footprint through three specialized binary files located in the `data/` directory.

- **`vector.bin`**: Stores raw vector data in an append-only structure.
- **`meta.bin`**: Maps external string IDs to internal file offsets using a disk-based Binary Search Tree (BST) for efficient lookups without loading maps into RAM.
- **`payload.bin`**: Stores serialized metadata (JSON) in an append-only structure referenced by `meta.bin`.
- **`graph.bin`**: Stores the HNSW Graph structure to enable fast navigation and approximate nearest neighbor searches.
- **Concurrency**: Implements advisory file locking to manage simultaneous shared reads and exclusive write operations safely.

---

## How to generate embeddings from a document?

Vektor stores vectors, but it does not generate them. You need an embedding model for that. A great local option is [Ollama](https://ollama.com/).

1. Install Ollama and pull an embedding model (e.g., `nomic-embed-text`).
2. Use the Ollama API to generate the vector for your text.
3. Pass that vector to Vektor:

```php
function getEmbedding(string $text): array {
    $ch = curl_init('http://localhost:11434/api/embeddings');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'nomic-embed-text',
        'prompt' => $text
    ]));

    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $response['embedding'];
}

// IMPORTANT: Vektor stores the ID and the Vector, but NOT the original content.
// You are responsible for storing the actual text (in files, S3, etc.).

// 1. Read your document
$id = "doc-hello";
$text = file_get_contents("{$id}.txt");

// 2. Generate vector
$vector = getEmbedding($text);

// 3. Insert into Vektor using the filename/ID as the reference
$indexer->insert($id, $vector);
```

---

## Troubleshooting

### 1. Permission Denied Errors
Ensure your PHP process (e.g., `www-data`) has read/write access to the `data/` folder and the files inside it.
```bash
chown -R www-data:www-data data/
chmod -R 775 data/
```

### 2. "Invalid Vector Dimensions"
Vektor defaults to **1536 dimensions**. If you send a vector with different dimensions, it will be rejected. 
To change this, you can use `VECTOR_DIMENSIONS` in your `.env` or `Config::setDimensions(N)` in your code.
**Important**: If you change dimensions, you must start with an empty data directory, as the binary file structure depends on the dimension size.

### 3. Slow Performance?
- **Disk I/O**: Since Vektor is disk-based, SSDs are highly recommended. HDDs will result in slow seek times.
- **Opcache**: Ensure PHP Opcache is enabled for production.

---

## Contributing

Contributions are welcome! Please run the test suite before submitting a PR.

```bash
composer test
```

The test suite includes Unit tests for storage engines and Feature tests for the HNSW logic.

---

## License

This project is licensed under the **MIT License**.
