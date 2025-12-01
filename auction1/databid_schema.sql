-- =====================================================
-- DataBid Auction Database Schema
-- Import this into your 'auction_db' database
-- =====================================================

-- =====================================================
-- Table: Users
-- =====================================================
DROP TABLE IF EXISTS `Notifications`;
DROP TABLE IF EXISTS `Watchlist`;
DROP TABLE IF EXISTS `AuctionCategories`;
DROP TABLE IF EXISTS `Bids`;
DROP TABLE IF EXISTS `Auctions`;
DROP TABLE IF EXISTS `Categories`;
DROP TABLE IF EXISTS `Users`;

CREATE TABLE `Users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('buyer', 'seller', 'both') NOT NULL DEFAULT 'buyer',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_username` (`username`),
    UNIQUE KEY `uniq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Table: Categories
-- =====================================================
CREATE TABLE `Categories` (
    `category_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(255) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Table: Auctions
-- =====================================================
CREATE TABLE `Auctions` (
    `auction_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `start_price` DECIMAL(10,2) NOT NULL,
    `reserve_price` DECIMAL(10,2) NULL,
    `buy_now_price` DECIMAL(10,2) NULL,
    `end_time` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fee_percentage` DECIMAL(5,2) NOT NULL DEFAULT 3.00,
    `fee_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `seller_payout` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `winner_user_id` INT NULL,
    `winner_notification_sent` TINYINT(1) NOT NULL DEFAULT 0,
    `winner_notified_at` DATETIME NULL,
    `bought_now` TINYINT(1) NOT NULL DEFAULT 0,
    `image_path` VARCHAR(255) NULL,
    FOREIGN KEY (`user_id`) REFERENCES `Users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_end_time` (`end_time`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Table: Bids
-- =====================================================
CREATE TABLE `Bids` (
    `bid_id` INT AUTO_INCREMENT PRIMARY KEY,
    `auction_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `bid_amount` DECIMAL(10,2) NOT NULL,
    `bid_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`auction_id`) REFERENCES `Auctions`(`auction_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `Users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_auction` (`auction_id`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Table: Watchlist
-- =====================================================
CREATE TABLE `Watchlist` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `auction_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_watch` (`user_id`, `auction_id`),
    FOREIGN KEY (`user_id`) REFERENCES `Users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`auction_id`) REFERENCES `Auctions`(`auction_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Table: Notifications
-- =====================================================
CREATE TABLE `Notifications` (
    `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `Users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Table: AuctionCategories (Junction table for many-to-many)
-- One auction can belong to multiple categories
-- =====================================================
CREATE TABLE `AuctionCategories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `auction_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `position` INT NOT NULL DEFAULT 0 COMMENT 'Order priority for this category',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `auction_category_unique` (`auction_id`, `category_id`),
    FOREIGN KEY (`auction_id`) REFERENCES `Auctions`(`auction_id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `Categories`(`category_id`) ON DELETE CASCADE,
    INDEX `idx_auction` (`auction_id`),
    INDEX `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Table: Ratings (for seller ratings - 1 to 5 stars)
-- =====================================================
CREATE TABLE `Ratings` (
    `rating_id` INT AUTO_INCREMENT PRIMARY KEY,
    `auction_id` INT NOT NULL,
    `seller_id` INT NOT NULL,
    `buyer_id` INT NOT NULL,
    `rating` TINYINT NOT NULL COMMENT '1-5 stars',
    `comment` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_auction_rating` (`auction_id`),
    FOREIGN KEY (`auction_id`) REFERENCES `Auctions`(`auction_id`) ON DELETE CASCADE,
    FOREIGN KEY (`seller_id`) REFERENCES `Users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`buyer_id`) REFERENCES `Users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_seller` (`seller_id`),
    INDEX `idx_buyer` (`buyer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Default Categories
-- =====================================================
INSERT INTO `Categories` (`category_name`) VALUES
    ('Electronics & Gadgets'),
    ('Home & Garden'),
    ('Fashion & Accessories'),
    ('Collectibles & Art'),
    ('Sports & Outdoors'),
    ('Automotive & Parts'),
    ('Books, Music & Media'),
    ('Toys & Games'),
    ('Health & Beauty'),
    ('Musical Instruments'),
    ('Audio & Headphones'),
    ('Computers & Laptops'),
    ('Mobile Phones'),
    ('Cameras & Photography'),
    ('Jewelry & Watches');

-- =====================================================
-- Import Complete!
-- =====================================================
