CREATE TABLE Customers (
    CustomerID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    CustomerName VARCHAR(100) NOT NULL,
    ContactName VARCHAR(100) NOT NULL,
    CAddress VARCHAR(500) NOT NULL,
    City VARCHAR(100) NOT NULL,
    PostalCode VARCHAR(20) NOT NULL DEFAULT '',
    Country VARCHAR(100) NOT NULL,
    CPassword VARCHAR(255) NOT NULL,
    CEmail VARCHAR(254) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    auth_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erased_at DATETIME NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY customers_retention (erased_at, last_seen_at),
    UNIQUE KEY customers_email (CEmail)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users_tb (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL DEFAULT '',
    email VARCHAR(254) NOT NULL,
    username VARCHAR(100) NOT NULL DEFAULT '',
    pass VARCHAR(255) NOT NULL,
    mobile VARCHAR(30) NOT NULL DEFAULT '',
    auth_version INT UNSIGNED NOT NULL DEFAULT 1,
    mfa_secret TEXT NULL,
    mfa_last_counter BIGINT NOT NULL DEFAULT -1,
    mfa_pending_secret TEXT NULL,
    mfa_pending_expires DATETIME NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Products (
    pcode INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pcategory VARCHAR(100) NOT NULL,
    pprice INT UNSIGNED NOT NULL,
    pimage VARCHAR(500) NOT NULL,
    pname VARCHAR(100) NOT NULL,
    pdesc VARCHAR(2000) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (pprice > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Orders (
    OrderID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    OrderDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CustomerID INT UNSIGNED NOT NULL,
    OrderStatus VARCHAR(10) NOT NULL,
    InvoiceExpiresAt DATETIME NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY orders_customer_status (CustomerID, OrderStatus),
    KEY orders_expiry (OrderStatus, InvoiceExpiresAt),
    CONSTRAINT orders_customer FOREIGN KEY (CustomerID) REFERENCES Customers(CustomerID) ON DELETE RESTRICT,
    CHECK (OrderStatus IN ('INVOICE', 'PAID', 'CANCELLED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE OrderDetails (
    OrderDetailsID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    OrderID INT UNSIGNED NOT NULL,
    pcode INT UNSIGNED NOT NULL,
    pprice INT UNSIGNED NOT NULL,
    qty INT UNSIGNED NOT NULL,
    pname VARCHAR(100) NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY order_product (OrderID, pcode),
    CONSTRAINT details_order FOREIGN KEY (OrderID) REFERENCES Orders(OrderID) ON DELETE CASCADE,
    CONSTRAINT details_product FOREIGN KEY (pcode) REFERENCES Products(pcode),
    CHECK (qty BETWEEN 1 AND 9999),
    CHECK (pprice > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS RateLimits (
    bucket_key CHAR(64) CHARACTER SET ascii PRIMARY KEY,
    hits INT UNSIGNED NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    KEY limiter_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS RecoveryCodes (
    token_hash CHAR(64) CHARACTER SET ascii PRIMARY KEY,
    account_kind VARCHAR(8) NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    KEY recovery_account (account_kind, account_id),
    KEY recovery_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS SecurityEvents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_name VARCHAR(64) NOT NULL,
    actor_kind VARCHAR(16) NOT NULL,
    actor_id INT UNSIGNED NULL,
    target_id INT UNSIGNED NULL,
    outcome VARCHAR(8) NOT NULL,
    request_id CHAR(24) CHARACTER SET ascii NOT NULL,
    KEY events_time (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ErasureLedger (
    customer_id INT UNSIGNED PRIMARY KEY,
    erased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE MaeSchema (version INT PRIMARY KEY);
INSERT INTO MaeSchema (version) VALUES (3);
