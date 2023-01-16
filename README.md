<p align="center"><a title="documentation" href="https://www.cinch.live" target="_blank"><img src="https://raw.githubusercontent.com/cinch-project/docs/master/logo/highres-name-web.png" width="275"></a></p>

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
