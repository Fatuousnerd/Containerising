FROM rust:1.98-slim-bookworm AS builder

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends build-essential \
    && rm -rf /var/lib/apt/lists/*

COPY Cargo.toml Cargo.lock ./
COPY src ./src
COPY templates ./templates

RUN cargo build --release

FROM debian:bookworm-slim

WORKDIR /app

COPY --from=builder /app/target/release/guestbook-rust /usr/local/bin/guestbook-rust

RUN mkdir -p /data \
    && useradd --system --uid 1001 app \
    && chown -R app:app /data

USER app

ENV DATABASE_PATH=/data/app.db

EXPOSE 8080

CMD ["guestbook-rust"]
