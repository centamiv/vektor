# Vektor - Native PHP Vector Database

**Vektor** is a purely file-based, embedded Vector Database written in native PHP. It is designed for "Zero-RAM Overhead" operation, meaning it doesn't load the entire dataset into memory. Instead, it relies on strict binary file layouts and disk-based seeking to perform approximate nearest neighbor searches using the HNSW (Hierarchical Navigable Small World) algorithm.

---

## API Endpoints

The system exposes a simple REST API.

### Insert (`POST /insert`)
Inserts a new vector into the database.
- **Payload:** `{"id": "doc-1", "vector": [0.1, ...]}`

### Search (`POST /search`)
Searches for the nearest neighbors of a given vector.
- **Payload:** `{"vector": [0.1, ...], "k": 5}`

### Delete (`POST /delete`)
Deletes a vector from the database.
- **Payload:** `{"id": "doc-1"}`

### Info (`GET /info`)
Returns DB statistics.
- **Response:**
  ```json
  {
      "storage": { "vector_file_bytes": ... },
      "records": { "vectors_total": ... },
      "config": { "dimension": 1536, ... }
  }
  ```

---

## Deployment

Requires **PHP 8.2+** and **Composer**.

```bash
composer install --no-dev
chmod -R 777 data/
```

Point your web server's document root to the `public/` directory, using the provided `.htaccess` (Apache) or `nginx.conf.example` (Nginx).

---

## License
MIT
