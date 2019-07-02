-- Prepare schema
DROP TABLE IF EXISTS customers;
CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT);

DROP TABLE IF EXISTS orders;
CREATE TABLE orders (id INTEGER PRIMARY KEY, customer_id INTEGER, date_created TEXT, date_deleted TEXT);

DROP TABLE IF EXISTS items;
CREATE TABLE items (id INTEGER PRIMARY KEY, order_id INTEGER, description TEXT, amount INTEGER, modified TEXT);

DROP TABLE IF EXISTS discounts;
CREATE TABLE discounts (id INTEGER PRIMARY KEY, order_id INTEGER, description TEXT, amount INTEGER);

-- Seed database
INSERT INTO customers (id, name) VALUES
(1, 'John Doe'),
(2, 'Jane Doe');

INSERT INTO orders (id, customer_id, date_created) VALUES
(1, 1, '2018-01-01'),
(2, 2, '2018-01-02'),
(3, 1, '2018-01-07');

INSERT INTO items (id, order_id, description, amount) VALUES
(1, 1, 'Milk', 300),
(2, 1, 'Eggs', 1200),
(3, 1, 'Bacon', 1200),
(4, 2, 'Bread', 120),
(5, 2, 'Yogurt', 400),
(6, 3, 'Apple', 100);

INSERT INTO discounts (id, order_id, description, amount) VALUES
(1, 2, '$10', 10);
