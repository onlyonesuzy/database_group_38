<?php
/**
 * schema_bootstrap.php
 * Runtime helpers that make sure the database schema contains the
 * columns/tables required for the enhanced auction functionality.
 *
 * This file is intentionally idempotent and safe to run on every request.
 */

if (!function_exists('bootstrapAuctionSchema')) {
    /**
     * Ensure that the Auctions table and related helpers contain
     * the columns we rely on throughout the UI.
     */
    function bootstrapAuctionSchema(mysqli $conn): void
    {
        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }

        $bootstrapped = true;

        $dbResult = $conn->query('SELECT DATABASE() as db');
        if (!$dbResult) {
            return;
        }

        $row = $dbResult->fetch_assoc();
        if (!$row || empty($row['db'])) {
            return;
        }

        $database = $row['db'];

        ensureCoreTables($conn, $database);

        ensureAuctionColumn($conn, $database, 'Auctions', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        ensureAuctionColumn($conn, $database, 'Auctions', 'fee_percentage', 'DECIMAL(5,2) NOT NULL DEFAULT 3.00');
        ensureAuctionColumn($conn, $database, 'Auctions', 'fee_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        ensureAuctionColumn($conn, $database, 'Auctions', 'seller_payout', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        ensureAuctionColumn($conn, $database, 'Auctions', 'winner_user_id', 'INT NULL');
        ensureAuctionColumn($conn, $database, 'Auctions', 'winner_notification_sent', 'TINYINT(1) NOT NULL DEFAULT 0');
        ensureAuctionColumn($conn, $database, 'Auctions', 'winner_notified_at', 'DATETIME NULL');
        ensureAuctionColumn($conn, $database, 'Auctions', 'buy_now_price', 'DECIMAL(10,2) NULL');
        ensureAuctionColumn($conn, $database, 'Auctions', 'bought_now', 'TINYINT(1) NOT NULL DEFAULT 0');
        ensureAuctionColumn($conn, $database, 'Auctions', 'image_path', 'VARCHAR(255) NULL');

        ensureNotificationsTable($conn, $database);
        ensureAuctionCategoriesTable($conn);
        ensureDefaultCategories($conn);
        
        // Password reset columns
        ensureAuctionColumn($conn, $database, 'Users', 'reset_token', 'VARCHAR(64) NULL');
        ensureAuctionColumn($conn, $database, 'Users', 'reset_token_expires', 'DATETIME NULL');
        
        // Seller ratings table
        ensureRatingsTable($conn, $database);
    }

    /**
     * Check if a column already exists on the provided table.
     */
    function auctionColumnExists(mysqli $conn, string $database, string $table, string $column): bool
    {
        $sql = 'SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('sss', $database, $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = false;
        if ($result) {
            $exists = ((int) $result->fetch_assoc()['cnt']) > 0;
        }
        $stmt->close();
        return $exists;
    }

    /**
     * Add a column to a table if it does not already exist.
     */
    function ensureAuctionColumn(mysqli $conn, string $database, string $table, string $column, string $definition): void
    {
        if (auctionColumnExists($conn, $database, $table, $column)) {
            return;
        }

        $conn->query("ALTER TABLE `{$table}` ADD COLUMN {$column} {$definition}");
    }

    function ensureTableExists(mysqli $conn, string $database, string $table): bool
    {
        $sql = 'SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $database, $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = false;
        if ($result) {
            $exists = ((int) $result->fetch_assoc()['cnt']) > 0;
        }
        $stmt->close();
        return $exists;
    }

    function ensureCoreTables(mysqli $conn, string $database): void
    {
        ensureUsersTable($conn, $database);
        ensureCategoriesTable($conn, $database);
        ensureAuctionsTable($conn, $database);
        ensureBidsTable($conn, $database);
        ensureWatchlistTable($conn, $database);
        ensureAuctionCategoriesTable($conn);
    }

    function ensureUsersTable(mysqli $conn, string $database): void
    {
        if (ensureTableExists($conn, $database, 'Users')) {
            return;
        }

        $conn->query('CREATE TABLE Users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM(\'buyer\', \'seller\', \'both\') NOT NULL DEFAULT \'buyer\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_username (username),
            UNIQUE KEY uniq_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    function ensureCategoriesTable(mysqli $conn, string $database): void
    {
        if (ensureTableExists($conn, $database, 'Categories')) {
            return;
        }

        $conn->query('CREATE TABLE Categories (
            category_id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    function ensureAuctionsTable(mysqli $conn, string $database): void
    {
        if (ensureTableExists($conn, $database, 'Auctions')) {
            return;
        }

        $conn->query('CREATE TABLE Auctions (
            auction_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            start_price DECIMAL(10,2) NOT NULL,
            reserve_price DECIMAL(10,2) NULL,
            end_time DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fee_percentage DECIMAL(5,2) NOT NULL DEFAULT 3.00,
            fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            seller_payout DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            winner_user_id INT NULL,
            winner_notification_sent TINYINT(1) NOT NULL DEFAULT 0,
            winner_notified_at DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    function ensureBidsTable(mysqli $conn, string $database): void
    {
        if (ensureTableExists($conn, $database, 'Bids')) {
            return;
        }

        $conn->query('CREATE TABLE Bids (
            bid_id INT AUTO_INCREMENT PRIMARY KEY,
            auction_id INT NOT NULL,
            user_id INT NOT NULL,
            bid_amount DECIMAL(10,2) NOT NULL,
            bid_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (auction_id) REFERENCES Auctions(auction_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    function ensureWatchlistTable(mysqli $conn, string $database): void
    {
        if (ensureTableExists($conn, $database, 'Watchlist')) {
            return;
        }

        $conn->query('CREATE TABLE Watchlist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            auction_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_watch (user_id, auction_id),
            FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (auction_id) REFERENCES Auctions(auction_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    /**
     * Ensure the Notifications table exists for in-app notifications.
     */
    function ensureNotificationsTable(mysqli $conn, string $database): void
    {
        if (ensureTableExists($conn, $database, 'Notifications')) {
            return;
        }

        $conn->query('CREATE TABLE Notifications (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
            INDEX idx_user_read (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    /**
     * Ensure the AuctionCategories pivot table exists for multi-category support.
     */
    function ensureAuctionCategoriesTable(mysqli $conn): void
    {
        $conn->query('CREATE TABLE IF NOT EXISTS AuctionCategories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            auction_id INT NOT NULL,
            category_id INT NOT NULL,
            position INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY auction_category_unique (auction_id, category_id),
            INDEX idx_category (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    /**
     * Seed the Categories table with a richer default set if entries are missing.
     */
    function ensureDefaultCategories(mysqli $conn): void
    {
        $defaults = [
            'Electronics & Gadgets',
            'Home & Garden',
            'Fashion & Accessories',
            'Collectibles & Art',
            'Sports & Outdoors',
            'Automotive & Parts',
            'Books, Music & Media',
            'Toys & Games',
            'Health & Beauty',
            'Musical Instruments'
        ];

        $checkStmt = $conn->prepare('SELECT category_id FROM Categories WHERE category_name = ? LIMIT 1');
        $insertStmt = $conn->prepare('INSERT INTO Categories (category_name) VALUES (?)');
        if (!$checkStmt || !$insertStmt) {
            if ($checkStmt) {
                $checkStmt->close();
            }
            if ($insertStmt) {
                $insertStmt->close();
            }
            return;
        }

        foreach ($defaults as $name) {
            $checkStmt->bind_param('s', $name);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows === 0) {
                $insertStmt->bind_param('s', $name);
                $insertStmt->execute();
            }

            $checkStmt->free_result();
        }

        $checkStmt->close();
        $insertStmt->close();
    }

    /**
     * Ensure the Ratings table exists for seller ratings.
     */
    function ensureRatingsTable(mysqli $conn, string $database): void
    {
        if (ensureTableExists($conn, $database, 'Ratings')) {
            // Check if we need to migrate from ENUM to INT
            $col_check = $conn->query("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = 'Ratings' AND COLUMN_NAME = 'rating'");
            if ($col_check) {
                $col_data = $col_check->fetch_assoc();
                if ($col_data && strpos($col_data['DATA_TYPE'], 'enum') !== false) {
                    // Migrate to INT rating (1-5 stars)
                    $conn->query("ALTER TABLE Ratings ADD COLUMN rating_stars TINYINT NOT NULL DEFAULT 5 AFTER rating");
                    $conn->query("UPDATE Ratings SET rating_stars = CASE WHEN rating = 'positive' THEN 5 WHEN rating = 'neutral' THEN 3 ELSE 1 END");
                    $conn->query("ALTER TABLE Ratings DROP COLUMN rating");
                    $conn->query("ALTER TABLE Ratings CHANGE rating_stars rating TINYINT NOT NULL");
                }
            }
            return;
        }

        $conn->query('CREATE TABLE Ratings (
            rating_id INT AUTO_INCREMENT PRIMARY KEY,
            auction_id INT NOT NULL,
            seller_id INT NOT NULL,
            buyer_id INT NOT NULL,
            rating TINYINT NOT NULL,
            comment TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_auction_rating (auction_id),
            FOREIGN KEY (auction_id) REFERENCES Auctions(auction_id) ON DELETE CASCADE,
            FOREIGN KEY (seller_id) REFERENCES Users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (buyer_id) REFERENCES Users(user_id) ON DELETE CASCADE,
            INDEX idx_seller (seller_id),
            INDEX idx_buyer (buyer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}
