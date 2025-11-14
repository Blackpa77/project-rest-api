<?php
class Database
{
    private $type;
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $sslmode;
    public $conn;

    public function __construct() {
        $this->type = getenv('DB_TYPE') ?: 'mysql'; // vercel pakai pgsql
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->port = getenv('DB_PORT') ?: '3306';
        $this->db_name = getenv('DB_NAME') ?: 'rest_api_db'; // Kita ubah ini saat lokal
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
        $this->sslmode = getenv('DB_SSLMODE') ?: ''; // vercel pakai require
    }

    public function connect()
    {
        $this->conn = null;
        
        // 1. Siapkan DSN (String Koneksi)
        $dsn = "";
        if ($this->type === 'pgsql') {
            // DSN untuk PostgreSQL (YANG LAMA)
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name};sslmode={$this->sslmode}";
        } else {
            // DSN untuk MySQL (Hapus 'sslmode' dari string)
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name}";
        }

        // 2. Siapkan Opsi PDO
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // 3. TAMBAHKAN OPSI SSL UNTUK MYSQL (jika bukan di localhost)
        // Ini adalah cara MySQL menangani SSL, sesuai panduan [cite: 2187-2188, 2389-2390, 2734-2738]
        if ($this->type === 'mysql' && $this->host !== 'localhost') {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            $options[PDO::MYSQL_ATTR_SSL_CA] = true;
        }

        try {
            // 4. Hubungkan menggunakan DSN dan Opsi
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                $options // <-- Masukkan Opsi di sini
            );
        
        } catch (PDOException $e) {
            // Jika database belum ada, buat dulu
            if (strpos($e->getMessage(), 'Unknown database') !== false) {
                try {
                    // Opsi koneksi sementara tanpa dbname
                    $tempOptions = $options;
                    if ($this->type === 'mysql') {
                        $tempDsn = "mysql:host={$this->host}";
                    } else {
                        $tempDsn = "pgsql:host={$this->host}";
                    }
                    
                    $tempConn = new PDO($tempDsn, $this->username, $this->password, $tempOptions);
                    $tempConn->exec("CREATE DATABASE IF NOT EXISTS {$this->db_name}");
                    $tempConn = null;

                    // Reconnect ke database yang baru dibuat
                    $this->conn = new PDO($dsn, $this->username, $this->password, $options);
                
                } catch (PDOException $eCreate) {
                    die(json_encode(["error" => "Gagal membuat database: " . $eCreate->getMessage()]));
                }
            } else {
                die(json_encode(["error" => "Koneksi gagal: " . $e->getMessage()]));
            }
        }

        $this->createTableIfNotExists();
        return $this->conn;
    }

    private function createTableIfNotExists()
    {
        if ($this->type === 'pgsql') {
            $sql = "
            CREATE TABLE IF NOT EXISTS mahasiswa (
                id SERIAL PRIMARY KEY,              -- AUTO_INCREMENT versi PostgreSQL
                nama VARCHAR(100) NOT NULL,
                jurusan VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            ";
        } else {
            $sql = "
            CREATE TABLE IF NOT EXISTS mahasiswa (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                jurusan VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
        }

        try {
            $this->conn->exec($sql);
        } catch (PDOException $e) {
            die(json_encode(["error" => "Gagal membuat tabel: " . $e->getMessage()]));
        }
    }
}