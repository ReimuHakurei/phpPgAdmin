-- One of most things phpPgAdmin can show, so the crawler has pages to visit.
-- Keep it working on PostgreSQL 9.6, the oldest version tested in CI.

CREATE SCHEMA shop;

CREATE TYPE shop.order_status AS ENUM ('new', 'paid', 'shipped');
CREATE TYPE shop.money_pair AS (amount numeric, currency char(3));
CREATE DOMAIN shop.email AS text CHECK (VALUE ~ '@');

CREATE TABLE shop.customer (
    id serial PRIMARY KEY,
    name text NOT NULL,
    email shop.email UNIQUE,
    created_at timestamptz NOT NULL DEFAULT now()
);
COMMENT ON TABLE shop.customer IS 'People who buy things';
COMMENT ON COLUMN shop.customer.name IS 'Full name';

CREATE TABLE shop.orders (
    id bigserial PRIMARY KEY,
    customer_id int NOT NULL REFERENCES shop.customer(id) ON DELETE CASCADE,
    status shop.order_status NOT NULL DEFAULT 'new',
    total numeric(12,2) CHECK (total >= 0),
    note text,
    data jsonb
);
CREATE INDEX orders_customer_idx ON shop.orders (customer_id);
CREATE INDEX orders_status_idx ON shop.orders (status) WHERE status <> 'shipped';

CREATE SEQUENCE shop.invoice_seq START 1000;

CREATE VIEW shop.open_orders AS
    SELECT o.id, c.name, o.total
    FROM shop.orders o JOIN shop.customer c ON c.id = o.customer_id
    WHERE o.status <> 'shipped';

CREATE MATERIALIZED VIEW shop.order_totals AS
    SELECT customer_id, sum(total) AS total FROM shop.orders GROUP BY customer_id;

CREATE FUNCTION shop.touch() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    NEW.note := coalesce(NEW.note, '') || '.';
    RETURN NEW;
END $$;

CREATE TRIGGER orders_touch BEFORE UPDATE ON shop.orders
    FOR EACH ROW EXECUTE PROCEDURE shop.touch();

CREATE FUNCTION shop.add(a int, b int) RETURNS int LANGUAGE sql IMMUTABLE AS 'SELECT a + b';

CREATE AGGREGATE shop.my_sum (int) (SFUNC = shop.add, STYPE = int, INITCOND = '0');

CREATE OPERATOR shop.=== (LEFTARG = int, RIGHTARG = int, PROCEDURE = shop.add);

CREATE RULE orders_no_delete AS ON DELETE TO shop.orders
    WHERE OLD.status = 'shipped' DO INSTEAD NOTHING;

CREATE TEXT SEARCH CONFIGURATION shop.simple_en (COPY = pg_catalog.english);

INSERT INTO shop.customer (name, email) VALUES
    ('Ana', 'ana@example.com'),
    ('Budi', 'budi@example.com'),
    ('Chen <script>', 'chen@example.com');

INSERT INTO shop.orders (customer_id, status, total, note, data) VALUES
    (1, 'new', 10.50, NULL, '{"items": 1}'),
    (1, 'paid', 99.00, 'gift', '{"items": 3}'),
    (2, 'shipped', 5.00, 'it''s quoted', NULL);

REFRESH MATERIALIZED VIEW shop.order_totals;

CREATE TABLE public.plain (id int, val text);
INSERT INTO public.plain SELECT g, md5(g::text) FROM generate_series(1, 100) g;

-- Big enough for browsing to use the planner's row estimate instead of counting
-- (browse_exact_count_limit is 100000).
CREATE TABLE public.big AS
    SELECT g AS id, md5(g::text) AS val FROM generate_series(1, 200000) g;
ALTER TABLE public.big ADD PRIMARY KEY (id);
ANALYZE public.big;

-- Values the data grid has to tell apart: NULL, empty string, timestamps with
-- fractional seconds and a time zone, long text that gets clipped.
CREATE TABLE public.oddities (
    id int PRIMARY KEY,
    label text,
    at timestamptz,
    at_local timestamp(6),
    long_text text
);
INSERT INTO public.oddities VALUES
    (1, NULL, '2024-02-29 13:45:12.345678+07', '2024-02-29 13:45:12.345678', repeat('long text ', 20) || 'END'),
    (2, '', '2024-03-01 00:00:00+00', '2024-03-01 00:00:00', ''),
    (3, '100% under_score', NULL, NULL, NULL);

CREATE ROLE reader LOGIN PASSWORD 'reader';
CREATE ROLE staff;
GRANT staff TO reader;
GRANT USAGE ON SCHEMA shop TO staff;
GRANT SELECT ON ALL TABLES IN SCHEMA shop TO staff;

CREATE DATABASE ppa_other;

-- A user that can only get into ppa_other, for tests/dblogin.py. Taking
-- CONNECT away from PUBLIC on the other databases is what makes a normal
-- server-wide login (which goes through "postgres") impossible for it.
CREATE ROLE scoped LOGIN PASSWORD 'scoped';
REVOKE CONNECT ON DATABASE postgres FROM PUBLIC;
REVOKE CONNECT ON DATABASE ppa_test FROM PUBLIC;
