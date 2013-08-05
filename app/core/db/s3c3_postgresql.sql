CREATE TABLE contexts (
    id BIGSERIAL,
    client VARCHAR(20) NOT NULL,
    token VARCHAR(128),
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires TIMESTAMP,
    completed TIMESTAMP,
    usage_status SMALLINT DEFAULT 0,
    PRIMARY KEY (id)
);
CREATE INDEX ON contexts (token);
CREATE TABLE configs (
    id SERIAL,
    ckey VARCHAR(40) NOT NULL UNIQUE,
    cval VARCHAR(100)
);
INSERT INTO configs (ckey) VALUES
('token.expire', 90),('token.length', 64),('token.strength', 'maximum'),('token.delete_on_load', 1);
