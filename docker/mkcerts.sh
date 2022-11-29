#!/usr/bin/env sh

# Please see: https://gist.github.com/achesco/b893fb55b90651cf5f4cc803b78e19fd.

# Self-signed dev-only. Uses mTLS: Mutual Authentication - both sides verify and present certificates

# server certificate
# uses cinch.local as server's CN, which must be manually mapped to 127.0.0.1 via /etc/hosts. localhost won't work
# due to mysql. when localhost is used as the hostname, mysql annoyingly forces the use of a unix socket. That
# won't work when connecting to a container.

umask u=rw,go= && openssl req -days 3650 -new -text -nodes -subj '/CN=cinch.local' -keyout server.key -out server.csr
umask u=rw,go= && openssl req -days 3650 -x509 -text -in server.csr -key server.key -out server.crt
umask u=rw,go= && cp server.crt root.crt

# client certificate
# uses database role name 'cinch' as the CN. This is required by postgresql for full certificate
# authentication: no password. The CN must match the ROLE connecting, or match a mapping in pg_ident.conf.
# In addition, pg_hba.conf needs an entry for "cert" authentication method.
#
# mysql doesn't support full cert-auth, however it is common practice to set CN to the DB role, followed by
# `alter user 'boo'@'%' require subject '/CN=boo'` -- password still required.

umask u=rw,go= && openssl req -days 3650 -new -nodes -subj '/CN=cinch' -keyout client.key -out client.csr
umask u=rw,go= && openssl x509 -days 3650 -req  -CAcreateserial -in client.csr -CA root.crt -CAkey server.key -out client.crt
rm client.csr server.csr root.srl

# Examples:

# psql "host=cinch.local port=6001 dbname=dev user=cinch sslmode=verify-full \
#  sslcert=client.crt sslkey=client.key sslrootcert=root.crt"

# mysql -u cinch -h cinch.local -P 6000 --ssl-mode=verify_identity \
#  --ssl-ca=root.crt --ssl-cert=client.crt --ssl-key=client.key dev -p