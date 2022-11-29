create user 'cinch'@'%' identified by 'cinch123';
grant all on *.* to 'cinch'@'%';

# for testing TLS only configs
create user 'tlscinch'@'%' identified by 'cinch123' require subject '/CN=cinch';
grant all on *.* to 'tlscinch'@'%';
