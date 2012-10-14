CREATE TABLE servers (
	id int primary key,
	name varchar(16) not null
);
INSERT INTO servers (id, name) VALUES (0, 'utopia');
INSERT INTO servers (id, name) VALUES (1, 'smp1');
INSERT INTO servers (id, name) VALUES (2, 'smp2');
INSERT INTO servers (id, name) VALUES (3, 'smp3');
INSERT INTO servers (id, name) VALUES (4, 'smp4');
INSERT INTO servers (id, name) VALUES (5, 'smp5');
INSERT INTO servers (id, name) VALUES (6, 'smp6');
INSERT INTO servers (id, name) VALUES (7, 'smp7');
INSERT INTO servers (id, name) VALUES (8, 'smp8');
INSERT INTO servers (id, name) VALUES (9, 'smp9');

CREATE TABLE worlds (
	id int primary key auto_increment,
	name varchar(24) not null
);
INSERT INTO worlds (name) VALUES ('wilderness');
INSERT INTO worlds (name) VALUES ('wilderness_nether');
INSERT INTO worlds (name) VALUES ('town');

--drop table readings;
CREATE TABLE readings (
	id int primary key auto_increment,
	ts datetime not null,
	json text not null,
	server_id int not null references servers(id),
	world_id int not null references worlds(id)
);