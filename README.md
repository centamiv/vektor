# Vektor - Native PHP Vector Database

**Vektor** is a high-performance, purely file-based, embedded Vector Database written entirely in native PHP. It is designed for **"Zero-RAM Overhead"**, meaning it does not require loading your entire dataset into memory to function. 

Instead of memory-heavy indexes, Vektor utilizes strict binary file layouts and optimized disk-seeking strategies to perform Approximate Nearest Neighbor (ANN) searches using the **HNSW** (Hierarchical Navigable Small World) algorithm.

---

## Features

- **Pure PHP**: No external dependencies or C extentions required. Runs on any standard PHP 8.2+ environment.
- **Zero-RAM Overhead**: Data is read directly from disk. Memory usage is constant regardless of dataset size.
- **HNSW Algorithm**: Efficient graph-based index for fast approximate nearest neighbor search.
- **Binary Storage**: Compact binary file formats for Vectors, Graph connections, and Metadata.
- **Embedded or Server**: Use it directly in your PHP code or run it as a standalone HTTP API server.
- **Thread-Safety**: Implements file locking (flock) to safely handle concurrent reads and writes.
- **Cosine Similarity**: Optimized distance metric for high-dimensional embeddings (default 1536 dimensions).

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
    ```

-   **VEKTOR_API_TOKEN**: If set, all API requests (except `/up`) must include this token in the `Authorization` header. If left empty, the API is open to the public.

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
    "meta_file_bytes": 2048
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
  "vector": [0.1, 0.2, 0.3, ...]
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
$indexer->insert($id, $vector);
```

#### 2. Searching

Find the `k` nearest neighbors to a query vector.

```php
$queryVector = [0.0123, ...];
$k = 5; // Number of results

$results = $searcher->search($queryVector, $k);

// Output:
// [
//   ['id' => 'doc-123', 'score' => 0.9823],
//   ['id' => 'doc-456', 'score' => 0.8912],
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

## Architecture Deep Dive

Vektor achieves its performance and low memory footprint through three specialized binary files located in the `data/` directory.

- **`vector.bin`**: Stores raw vector data in an append-only structure.
- **`meta.bin`**: Maps external string IDs to internal file offsets using a disk-based Binary Search Tree (BST) for efficient lookups without loading maps into RAM.
- **`graph.bin`**: Stores the HNSW Graph structure to enable fast navigation and approximate nearest neighbor searches.
- **Concurrency**: Implements advisory file locking to manage simultaneous shared reads and exclusive write operations safely.

---

## Troubleshooting

### 1. Permission Denied Errors
Ensure your PHP process (e.g., `www-data`) has read/write access to the `data/` folder and the files inside it.
```bash
chown -R www-data:www-data data/
chmod -R 775 data/
```

### 2. "Invalid Vector Dimensions"
Vektor is hardcoded for **1536 dimensions**. If you send a vector with 1535 or 1537 dimensions, it will be rejected. 
To change this, you must modify `src/Core/Config.php` and rebuild/truncate your data files, as the binary stride will change.

### 3. Slow Performance?
- **Disk I/O**: Since Vektor is disk-based, SSDs are highly recommended. HDDs will result in slow seek times.
- **Opcache**: Ensure PHP Opcache is enabled for production.

---

## 🤝 Contributing

Contributions are welcome! Please run the test suite before submitting a PR.

```bash
composer test
```

The test suite includes Unit tests for storage engines and Feature tests for the HNSW logic.

---

## 📜 License

This project is licensed under the **MIT License**.
