import Database from "better-sqlite3";
import { mkdirSync } from "node:fs";
import { dirname } from "node:path";

export type Entry = {
  id: number;
  name: string;
  message: string;
  created_at: string;
};

let database: Database.Database | undefined;

export function getDb(): Database.Database {
  if (!database) {
    const path = process.env.DATABASE_PATH ?? "data/app.db";
    mkdirSync(dirname(path), { recursive: true });
    database = new Database(path);
    database.pragma("journal_mode = WAL");
    database.exec(
      `CREATE TABLE IF NOT EXISTS entries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        message TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
      )`,
    );
  }

  return database;
}
