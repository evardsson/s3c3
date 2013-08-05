CREATE TABLE contexts (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    client VARCHAR(20) NOT NULL,
    token VARCHAR(128),
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires TIMESTAMP,
    completed TIMESTAMP,
    usage_status SMALLINT DEFAULT 0
);
CREATE INDEX token_idx ON contexts (token);
CREATE TABLE configs (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    ckey VARCHAR(40) NOT NULL UNIQUE,
    cval VARCHAR(100)
);
INSERT INTO configs (ckey) VALUES
('token.expire', 90),('token.length', 64),('token.strength', 'maximum'),('token.delete_on_load', 1);
