create role cinch createdb login password 'cinch123';
alter role cinch set search_path to public;
alter database dev owner to cinch;
