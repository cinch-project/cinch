# Cinch Database Migration System

Please see documentation: https://www.cinch.live

<!--
```yaml
migration_store: file://.           # default=file:/.
single_transaction: true            # default=true - group migrations within a single transaction
environments:
    default: sales
    sales:
        deploy_timeout: 10           # default=10
        target: 'pgsql://user:pass@127.0.0.1/sales'
        history:
            dsn: 'mssql://user:pass@127.0.0.1/history' # default=target_dsn
            schema: ''               # default=cinch_project
            table_prefix: ''         # default=''
            create_schema: true      # default=true
hooks: []
```
-->