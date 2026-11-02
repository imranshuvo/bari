# Nightly backups of the database and uploaded bill photos into the "backups" volume.
# Same Postgres version as the db service, so pg_dump always matches the server.
FROM postgres:17-alpine
COPY backup-now backup-restore backup-start /usr/local/bin/
RUN chmod +x /usr/local/bin/backup-now /usr/local/bin/backup-restore /usr/local/bin/backup-start
ENTRYPOINT ["backup-start"]
