-- dbSetup.sql creates the BEST (Books-Extraordinary-Supply-Town) database
-- written for MySQL 8 / TiDB (MySQL compatible). TiDB has no triggers or stored procedures,
-- so all stock and order logic lives in the PHP helpers and runs inside transactions.
-- run the whole file once on an empty database. It is safe to re-run: it drops the tables first.

CREATE DATABASE IF NOT EXISTS best_books;
USE best_books;

-- drop in reverse order of the foreign keys so a re-run starts clean
DROP TABLE IF EXISTS ActivityLog;
DROP TABLE IF EXISTS ContactMessage;
DROP TABLE IF EXISTS StockMovement;
DROP TABLE IF EXISTS OrderStatusHistory;
DROP TABLE IF EXISTS OrderItem;
DROP TABLE IF EXISTS CustomerOrder;
DROP TABLE IF EXISTS Inventory;
DROP TABLE IF EXISTS Book;
DROP TABLE IF EXISTS Category;
DROP TABLE IF EXISTS Admin;
DROP TABLE IF EXISTS Staff;
DROP TABLE IF EXISTS Customer;
DROP TABLE IF EXISTS Store;
DROP TABLE IF EXISTS UserAccount;

-- ---------------------------------------------------------------------------
-- People
-- UserAccount is the supertype. Every account is exactly one of Customer, Staff or Admin
-- (disjoint, total specialisation). The role comes from whichever subtype table holds the account.
-- ---------------------------------------------------------------------------

CREATE TABLE UserAccount (
  accountID INT PRIMARY KEY AUTO_INCREMENT,
  firstName VARCHAR(100) NOT NULL,
  lastName VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  phoneNumber VARCHAR(30) NULL,
  passwordHash VARCHAR(255) NOT NULL,
  accountStatus ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  -- staff accounts made by an admin get a temporary password and must change it on first login
  mustChangePassword BOOLEAN NOT NULL DEFAULT FALSE,
  createdBy INT NULL,
  dateCreated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lastLogin DATETIME NULL,
  CONSTRAINT fk_account_createdby FOREIGN KEY (createdBy) REFERENCES UserAccount (accountID) ON DELETE SET NULL,
  INDEX idx_account_status (accountStatus)
);

CREATE TABLE Store (
  storeID INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL UNIQUE,
  addressLine VARCHAR(255) NOT NULL,
  city VARCHAR(100) NOT NULL,
  province VARCHAR(60) NOT NULL,
  postalCode VARCHAR(10) NOT NULL,
  phoneNumber VARCHAR(30) NOT NULL,
  email VARCHAR(255) NOT NULL,
  openingHours VARCHAR(255) NOT NULL,
  isActive BOOLEAN NOT NULL DEFAULT TRUE,
  dateCreated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Customer (
  accountID INT PRIMARY KEY,
  addressLine VARCHAR(255) NULL,
  suburb VARCHAR(100) NULL,
  city VARCHAR(100) NULL,
  province VARCHAR(60) NULL,
  postalCode VARCHAR(10) NULL,
  -- POPIA: when the customer accepted the privacy notice, and whether they opted in to marketing
  privacyConsentAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  marketingConsent BOOLEAN NOT NULL DEFAULT FALSE,
  CONSTRAINT fk_customer_account FOREIGN KEY (accountID) REFERENCES UserAccount (accountID) ON DELETE CASCADE
);

CREATE TABLE Staff (
  accountID INT PRIMARY KEY,
  -- the store a staff member works at. Staff can only change stock for their own store.
  storeID INT NULL,
  jobTitle VARCHAR(100) NOT NULL DEFAULT 'Store Assistant',
  CONSTRAINT fk_staff_account FOREIGN KEY (accountID) REFERENCES UserAccount (accountID) ON DELETE CASCADE,
  CONSTRAINT fk_staff_store FOREIGN KEY (storeID) REFERENCES Store (storeID) ON DELETE SET NULL
);

CREATE TABLE Admin (
  accountID INT PRIMARY KEY,
  CONSTRAINT fk_admin_account FOREIGN KEY (accountID) REFERENCES UserAccount (accountID) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------------
-- Products and multi-location stock:  Book -< Inventory >- Store
-- ---------------------------------------------------------------------------

CREATE TABLE Category (
  categoryID INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  icon VARCHAR(50) NOT NULL DEFAULT 'bi-book',
  sortOrder INT NOT NULL DEFAULT 0,
  isActive BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE Book (
  bookID INT PRIMARY KEY AUTO_INCREMENT,
  categoryID INT NOT NULL,
  isbn VARCHAR(13) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  author VARCHAR(255) NOT NULL,
  publisher VARCHAR(150) NULL,
  publishYear SMALLINT NULL,
  pageCount INT NULL,
  language VARCHAR(40) NOT NULL DEFAULT 'English',
  format ENUM('Paperback', 'Hardcover', 'Board book') NOT NULL DEFAULT 'Paperback',
  description TEXT NOT NULL,
  price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
  -- empty means the cover is looked up from Open Library by ISBN
  coverImageURL VARCHAR(500) NULL,
  isFeatured BOOLEAN NOT NULL DEFAULT FALSE,
  -- hidden books stay in the database so old orders still point at them
  isActive BOOLEAN NOT NULL DEFAULT TRUE,
  dateAdded DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dateUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_book_category FOREIGN KEY (categoryID) REFERENCES Category (categoryID),
  INDEX idx_book_title (title),
  INDEX idx_book_author (author),
  INDEX idx_book_active (isActive, isFeatured)
);

-- one row per book per store. This is what lets BEST see 10 at Store A, 4 at Store B and 0 at Store C.
CREATE TABLE Inventory (
  bookID INT NOT NULL,
  storeID INT NOT NULL,
  quantity INT NOT NULL DEFAULT 0 CHECK (quantity >= 0),
  reorderLevel INT NOT NULL DEFAULT 3 CHECK (reorderLevel >= 0),
  lastUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (bookID, storeID),
  CONSTRAINT fk_inventory_book FOREIGN KEY (bookID) REFERENCES Book (bookID) ON DELETE CASCADE,
  CONSTRAINT fk_inventory_store FOREIGN KEY (storeID) REFERENCES Store (storeID) ON DELETE CASCADE,
  INDEX idx_inventory_store (storeID)
);

-- ---------------------------------------------------------------------------
-- Orders
-- ---------------------------------------------------------------------------

CREATE TABLE CustomerOrder (
  orderID INT PRIMARY KEY AUTO_INCREMENT,
  orderNumber VARCHAR(24) NOT NULL UNIQUE,
  customerID INT NOT NULL,
  orderDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending', 'processing', 'ready', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  fulfilmentType ENUM('delivery', 'collection') NOT NULL,
  -- set for click-and-collect orders
  collectionStoreID INT NULL,
  -- copied onto the order so a later address change does not rewrite history
  recipientName VARCHAR(200) NOT NULL,
  contactPhone VARCHAR(30) NOT NULL,
  deliveryAddress VARCHAR(255) NULL,
  deliverySuburb VARCHAR(100) NULL,
  deliveryCity VARCHAR(100) NULL,
  deliveryProvince VARCHAR(60) NULL,
  deliveryPostalCode VARCHAR(10) NULL,
  subtotal DECIMAL(10, 2) NOT NULL,
  deliveryFee DECIMAL(10, 2) NOT NULL DEFAULT 0,
  total DECIMAL(10, 2) NOT NULL,
  paymentMethod ENUM('eft', 'card_on_collection', 'cash_on_collection', 'card_on_delivery') NOT NULL,
  paymentStatus ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',
  customerNote VARCHAR(500) NULL,
  handledBy INT NULL,
  dateUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_customer FOREIGN KEY (customerID) REFERENCES Customer (accountID),
  CONSTRAINT fk_order_store FOREIGN KEY (collectionStoreID) REFERENCES Store (storeID),
  CONSTRAINT fk_order_handledby FOREIGN KEY (handledBy) REFERENCES UserAccount (accountID) ON DELETE SET NULL,
  INDEX idx_order_status (status, orderDate),
  INDEX idx_order_customer (customerID, orderDate)
);

-- storeID says which store the copies were taken from. A delivery order can be filled from more
-- than one store, so the same book can appear on two rows with different stores.
CREATE TABLE OrderItem (
  orderItemID INT PRIMARY KEY AUTO_INCREMENT,
  orderID INT NOT NULL,
  bookID INT NOT NULL,
  storeID INT NOT NULL,
  quantity INT NOT NULL CHECK (quantity > 0),
  unitPrice DECIMAL(10, 2) NOT NULL,
  CONSTRAINT fk_item_order FOREIGN KEY (orderID) REFERENCES CustomerOrder (orderID) ON DELETE CASCADE,
  CONSTRAINT fk_item_book FOREIGN KEY (bookID) REFERENCES Book (bookID),
  CONSTRAINT fk_item_store FOREIGN KEY (storeID) REFERENCES Store (storeID),
  UNIQUE KEY uq_item_order_book_store (orderID, bookID, storeID)
);

CREATE TABLE OrderStatusHistory (
  historyID INT PRIMARY KEY AUTO_INCREMENT,
  orderID INT NOT NULL,
  status VARCHAR(20) NOT NULL,
  note VARCHAR(255) NULL,
  changedBy INT NULL,
  changedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_order FOREIGN KEY (orderID) REFERENCES CustomerOrder (orderID) ON DELETE CASCADE,
  CONSTRAINT fk_history_account FOREIGN KEY (changedBy) REFERENCES UserAccount (accountID) ON DELETE SET NULL
);

-- every change to a stock level, so staff can see why a number moved
CREATE TABLE StockMovement (
  movementID INT PRIMARY KEY AUTO_INCREMENT,
  bookID INT NOT NULL,
  storeID INT NOT NULL,
  quantityChange INT NOT NULL,
  reason ENUM('sale', 'restock', 'adjustment', 'cancellation', 'initial') NOT NULL,
  orderID INT NULL,
  accountID INT NULL,
  note VARCHAR(255) NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_movement_book FOREIGN KEY (bookID) REFERENCES Book (bookID) ON DELETE CASCADE,
  CONSTRAINT fk_movement_store FOREIGN KEY (storeID) REFERENCES Store (storeID) ON DELETE CASCADE,
  CONSTRAINT fk_movement_order FOREIGN KEY (orderID) REFERENCES CustomerOrder (orderID) ON DELETE SET NULL,
  CONSTRAINT fk_movement_account FOREIGN KEY (accountID) REFERENCES UserAccount (accountID) ON DELETE SET NULL,
  INDEX idx_movement_book_store (bookID, storeID, createdAt)
);

-- ---------------------------------------------------------------------------
-- Support
-- ---------------------------------------------------------------------------

CREATE TABLE ContactMessage (
  messageID INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phoneNumber VARCHAR(30) NULL,
  subject VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new', 'replied', 'closed') NOT NULL DEFAULT 'new',
  handledBy INT NULL,
  dateCreated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_message_handledby FOREIGN KEY (handledBy) REFERENCES UserAccount (accountID) ON DELETE SET NULL,
  INDEX idx_message_status (status, dateCreated)
);

-- everything staff do in the portal. accountName is a copy so the log still reads correctly
-- after an account changes, targetID has no foreign key so a row survives the record being removed
CREATE TABLE ActivityLog (
  logID INT PRIMARY KEY AUTO_INCREMENT,
  accountID INT NULL,
  accountName VARCHAR(255) NOT NULL,
  category VARCHAR(30) NOT NULL,
  action VARCHAR(30) NOT NULL,
  description VARCHAR(500) NOT NULL,
  targetID INT NULL,
  ipAddress VARCHAR(45) NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_account FOREIGN KEY (accountID) REFERENCES UserAccount (accountID) ON DELETE SET NULL,
  INDEX idx_log_created (createdAt),
  INDEX idx_log_category (category, createdAt)
);

-- ===========================================================================
-- Seed data
-- ===========================================================================

INSERT INTO Store (storeID, name, addressLine, city, province, postalCode, phoneNumber, email, openingHours) VALUES
  (1, 'BEST Midrand',   'Shop 14, Waterfall Lifestyle Centre, Maxwell Drive', 'Midrand',      'Gauteng',       '1685', '+27 11 555 0101', 'midrand@best.co.za',   'Mon–Sat 09:00–18:00, Sun 09:00–14:00'),
  (2, 'BEST Sandton',   '22 Rivonia Road, Sandhurst',                         'Sandton',      'Gauteng',       '2196', '+27 11 555 0202', 'sandton@best.co.za',   'Mon–Sat 09:00–19:00, Sun 10:00–15:00'),
  (3, 'BEST Pretoria',  'Shop 8, Hatfield Square, Burnett Street',            'Pretoria',     'Gauteng',       '0083', '+27 12 555 0303', 'pretoria@best.co.za',  'Mon–Fri 08:30–17:30, Sat 09:00–14:00'),
  (4, 'BEST Cape Town', '41 Long Street, City Centre',                        'Cape Town',    'Western Cape',  '8001', '+27 21 555 0404', 'capetown@best.co.za',  'Mon–Sat 09:00–18:00, Sun 10:00–14:00');

INSERT INTO Category (categoryID, name, description, icon, sortOrder) VALUES
  (1,  'Fiction',                 'Contemporary novels and literary fiction',           'bi-book',             1),
  (2,  'Classics',                'Timeless works every reader should know',            'bi-bookmark-star',    2),
  (3,  'African Literature',      'Stories from and about the continent',               'bi-globe-europe-africa', 3),
  (4,  'Biography & Memoir',      'Real lives, in their own words',                     'bi-person-badge',     4),
  (5,  'Non-Fiction',             'History, science and ideas',                         'bi-lightbulb',        5),
  (6,  'Self-Help & Business',    'Habits, money and work',                             'bi-graph-up-arrow',   6),
  (7,  'Science Fiction & Fantasy','Other worlds and possible futures',                 'bi-stars',            7),
  (8,  'Mystery & Thriller',      'Page-turners that keep you guessing',                'bi-incognito',        8),
  (9,  'Children''s',             'Picture books and early readers',                    'bi-balloon',          9),
  (10, 'Technology & Computing',  'Programming, software and the craft of code',        'bi-cpu',              10);

INSERT INTO Book (bookID, categoryID, isbn, title, author, publisher, publishYear, pageCount, format, description, price, isFeatured) VALUES
  (1,  2, '9780061120084', 'To Kill a Mockingbird', 'Harper Lee', 'Harper Perennial', 2006, 336, 'Paperback', 'Scout Finch grows up in 1930s Alabama while her father, a lawyer, defends a Black man falsely accused of a crime. A classic about justice, prejudice and growing up.', 249.00, TRUE),
  (2,  2, '9780451524935', 'Nineteen Eighty-Four', 'George Orwell', 'Signet Classics', 1961, 328, 'Paperback', 'Winston Smith works for the Party in a state that watches everything and rewrites the past. Orwell''s warning about surveillance and control is as sharp as ever.', 199.00, FALSE),
  (3,  2, '9780743273565', 'The Great Gatsby', 'F. Scott Fitzgerald', 'Scribner', 2004, 180, 'Paperback', 'Nick Carraway is drawn into the glittering world of his mysterious neighbour Jay Gatsby on Long Island in the summer of 1922.', 189.00, FALSE),
  (4,  2, '9780141439518', 'Pride and Prejudice', 'Jane Austen', 'Penguin Classics', 2003, 480, 'Paperback', 'Elizabeth Bennet and Mr Darcy misjudge each other spectacularly in Austen''s sharp, funny novel of manners, money and marriage.', 179.00, FALSE),
  (5,  7, '9780547928227', 'The Hobbit', 'J.R.R. Tolkien', 'Mariner Books', 2012, 300, 'Paperback', 'Bilbo Baggins is swept out of his comfortable hobbit-hole on an adventure with thirteen dwarves and a wizard to reclaim a dragon''s treasure.', 259.00, TRUE),
  (6,  7, '9780441172719', 'Dune', 'Frank Herbert', 'Ace', 1990, 896, 'Paperback', 'On the desert planet Arrakis, young Paul Atreides is caught in a struggle over the most valuable substance in the universe.', 299.00, TRUE),
  (7,  1, '9780062315007', 'The Alchemist', 'Paulo Coelho', 'HarperOne', 2014, 208, 'Paperback', 'Santiago, a shepherd boy, travels from Spain to the Egyptian desert in search of treasure and learns to listen to his heart.', 229.00, FALSE),
  (8,  5, '9780062316097', 'Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', 'Harper', 2015, 464, 'Paperback', 'How did Homo sapiens come to rule the planet? A sweeping look at the cognitive, agricultural and scientific revolutions.', 329.00, TRUE),
  (9,  6, '9780735211292', 'Atomic Habits', 'James Clear', 'Avery', 2018, 320, 'Hardcover', 'A practical guide to building good habits and breaking bad ones through tiny changes that compound over time.', 379.00, TRUE),
  (10, 4, '9780316548182', 'Long Walk to Freedom', 'Nelson Mandela', 'Little, Brown and Company', 1995, 656, 'Paperback', 'Nelson Mandela''s autobiography, from his childhood in the Transkei to 27 years in prison and his election as South Africa''s first democratic president.', 349.00, TRUE),
  (11, 4, '9780399588174', 'Born a Crime', 'Trevor Noah', 'Spiegel & Grau', 2016, 304, 'Hardcover', 'Trevor Noah''s funny and moving account of growing up in South Africa under apartheid and in the years that followed.', 319.00, TRUE),
  (12, 3, '9780385474542', 'Things Fall Apart', 'Chinua Achebe', 'Anchor', 1994, 209, 'Paperback', 'Okonkwo, a proud Igbo leader, watches his world change with the arrival of colonial rule and Christian missionaries.', 219.00, FALSE),
  (13, 3, '9780743262170', 'Cry, the Beloved Country', 'Alan Paton', 'Scribner', 2003, 320, 'Paperback', 'A Zulu pastor travels to Johannesburg to find his son in this landmark novel of 1940s South Africa.', 229.00, FALSE),
  (14, 8, '9780307474278', 'The Da Vinci Code', 'Dan Brown', 'Anchor', 2009, 597, 'Paperback', 'A murder in the Louvre leads symbologist Robert Langdon into a race to uncover a secret guarded for centuries.', 239.00, FALSE),
  (15, 7, '9780439023481', 'The Hunger Games', 'Suzanne Collins', 'Scholastic Press', 2008, 374, 'Paperback', 'In the ruins of North America, Katniss Everdeen volunteers to take her sister''s place in a televised fight to the death.', 209.00, FALSE),
  (16, 4, '9781524763138', 'Becoming', 'Michelle Obama', 'Crown', 2018, 448, 'Hardcover', 'The former First Lady of the United States tells her story, from Chicago''s South Side to the White House.', 399.00, FALSE),
  (17, 4, '9780399590504', 'Educated', 'Tara Westover', 'Random House', 2018, 352, 'Hardcover', 'Raised by survivalists in Idaho with no schooling, Tara Westover taught herself enough to get into university and eventually earn a PhD.', 329.00, FALSE),
  (18, 5, '9780374533557', 'Thinking, Fast and Slow', 'Daniel Kahneman', 'Farrar, Straus and Giroux', 2013, 512, 'Paperback', 'A Nobel-winning psychologist explains the two systems that drive the way we think, and how they lead us astray.', 309.00, FALSE),
  (19, 10, '9780132350884', 'Clean Code', 'Robert C. Martin', 'Prentice Hall', 2008, 464, 'Paperback', 'A handbook of agile software craftsmanship: how to write code that is readable, testable and easy to change.', 699.00, FALSE),
  (20, 10, '9780135957059', 'The Pragmatic Programmer', 'David Thomas & Andrew Hunt', 'Addison-Wesley', 2019, 352, 'Hardcover', 'The 20th anniversary edition of the classic guide to becoming a better, more effective developer.', 749.00, TRUE),
  (21, 6, '9781612680194', 'Rich Dad Poor Dad', 'Robert T. Kiyosaki', 'Plata Publishing', 2017, 336, 'Paperback', 'What the rich teach their kids about money that the poor and middle class do not, told through the author''s two father figures.', 229.00, FALSE),
  (22, 9, '9780399226908', 'The Very Hungry Caterpillar', 'Eric Carle', 'Philomel Books', 1994, 26, 'Board book', 'A tiny caterpillar eats his way through a week of food before becoming a beautiful butterfly. A favourite first book.', 159.00, FALSE),
  (23, 1, '9780735219106', 'Where the Crawdads Sing', 'Delia Owens', 'G.P. Putnam''s Sons', 2018, 384, 'Paperback', 'Kya Clark, the "Marsh Girl", raises herself in the wetlands of North Carolina until a local man is found dead.', 269.00, FALSE),
  (24, 8, '9781250301697', 'The Silent Patient', 'Alex Michaelides', 'Celadon Books', 2019, 336, 'Paperback', 'Alicia Berenson shoots her husband and never speaks another word. Her psychotherapist is determined to find out why.', 249.00, FALSE);

-- give every book a stock level at every store. The formula spreads the numbers so some stores
-- have plenty, some have a few and some have none, which shows off the multi-location view.
INSERT INTO Inventory (bookID, storeID, quantity, reorderLevel)
SELECT b.bookID, s.storeID, MOD(b.bookID * 7 + s.storeID * 5, 13), 3
FROM Book b CROSS JOIN Store s;

INSERT INTO StockMovement (bookID, storeID, quantityChange, reason, note)
SELECT bookID, storeID, quantity, 'initial', 'Opening stock count'
FROM Inventory WHERE quantity > 0;

-- demo accounts, change these passwords after the first login (see README.md)
--   admin@best.co.za     BestAdmin#2026
--   staff@best.co.za     BestStaff#2026
--   reader@example.com   BestReader#2026
INSERT INTO UserAccount (accountID, firstName, lastName, email, phoneNumber, passwordHash) VALUES
  (1, 'Tshepo',  'Mokoena', 'admin@best.co.za',   '+27 82 000 0001', '$2y$10$5j8z0zNhbqbP3OgYWergI.5JUgKGenwth0IAOhzi14R/PE8BCIXtu'),
  (2, 'Lerato',  'Dlamini', 'staff@best.co.za',   '+27 82 000 0002', '$2y$10$qKvndm53L0IwnnvzF.nRce4C0sFKwFQisS7vfJMOxicaATAznhAT.'),
  (3, 'Sipho',   'Nkosi',   'reader@example.com', '+27 82 000 0003', '$2y$10$Z2uxUfTKVk/bsRtDH9gMfOUsiw79iX6XMyzPaUdSxCLNStGznMidi');

INSERT INTO Admin (accountID) VALUES (1);
INSERT INTO Staff (accountID, storeID, jobTitle) VALUES (2, 1, 'Store Manager');
INSERT INTO Customer (accountID, addressLine, suburb, city, province, postalCode, marketingConsent) VALUES
  (3, '12 Jacaranda Street', 'Halfway House', 'Midrand', 'Gauteng', '1685', FALSE);
