use std::{
    error::Error,
    path::Path as FsPath,
    sync::{Arc, Mutex},
};

use axum::{
    extract::{Form, Path, State},
    http::StatusCode,
    response::{Html, IntoResponse, Redirect, Response},
    routing::{get, post},
    Router,
};
use minijinja::{context, value::Serde, Environment};
use rusqlite::{params, Connection};
use serde::{Deserialize, Serialize};

#[derive(Clone)]
struct AppState {
    db: Arc<Mutex<Connection>>,
    env: Arc<Environment<'static>>,
}

#[derive(Serialize)]
struct Entry {
    id: i64,
    name: String,
    message: String,
    created_at: String,
}

#[derive(Deserialize)]
struct NewEntry {
    name: String,
    message: String,
}

#[derive(Debug)]
struct AppError(String);

impl From<rusqlite::Error> for AppError {
    fn from(error: rusqlite::Error) -> Self {
        Self(error.to_string())
    }
}

impl From<minijinja::Error> for AppError {
    fn from(error: minijinja::Error) -> Self {
        Self(error.to_string())
    }
}

impl IntoResponse for AppError {
    fn into_response(self) -> Response {
        (StatusCode::INTERNAL_SERVER_ERROR, self.0).into_response()
    }
}

fn template_env() -> Result<Environment<'static>, minijinja::Error> {
    let mut env = Environment::new();
    env.add_template("index.html", include_str!("../templates/index.html"))?;
    Ok(env)
}

async fn index(State(state): State<AppState>) -> Result<Html<String>, AppError> {
    let conn = state.db.lock().unwrap();
    let mut statement =
        conn.prepare("SELECT id, name, message, created_at FROM entries ORDER BY id DESC")?;
    let entries = statement
        .query_map([], |row| {
            Ok(Entry {
                id: row.get(0)?,
                name: row.get(1)?,
                message: row.get(2)?,
                created_at: row.get(3)?,
            })
        })?
        .collect::<Result<Vec<_>, _>>()?;
    let html = state
        .env
        .get_template("index.html")?
        .render(context! { entries => Serde(&entries) })?;
    Ok(Html(html))
}

async fn add(
    State(state): State<AppState>,
    Form(entry): Form<NewEntry>,
) -> Result<Redirect, AppError> {
    let name = entry.name.trim();
    let message = entry.message.trim();
    if !name.is_empty() && !message.is_empty() {
        state.db.lock().unwrap().execute(
            "INSERT INTO entries (name, message) VALUES (?1, ?2)",
            params![name, message],
        )?;
    }
    Ok(Redirect::to("/"))
}

async fn delete(State(state): State<AppState>, Path(id): Path<i64>) -> Result<Redirect, AppError> {
    state
        .db
        .lock()
        .unwrap()
        .execute("DELETE FROM entries WHERE id = ?1", [id])?;
    Ok(Redirect::to("/"))
}

#[tokio::main]
async fn main() -> Result<(), Box<dyn Error>> {
    let database_path =
        std::env::var("DATABASE_PATH").unwrap_or_else(|_| "data/app.db".to_string());
    if let Some(parent) = FsPath::new(&database_path).parent() {
        std::fs::create_dir_all(parent)?;
    }

    let connection = Connection::open(&database_path)?;
    connection.execute_batch(
        "PRAGMA journal_mode = WAL;
         CREATE TABLE IF NOT EXISTS entries (
             id INTEGER PRIMARY KEY AUTOINCREMENT,
             name TEXT NOT NULL,
             message TEXT NOT NULL,
             created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
         );",
    )?;

    let state = AppState {
        db: Arc::new(Mutex::new(connection)),
        env: Arc::new(template_env()?),
    };

    let app = Router::new()
        .route("/", get(index).post(add))
        .route("/delete/{id}", post(delete))
        .with_state(state);

    let listener = tokio::net::TcpListener::bind("0.0.0.0:8080").await?;
    axum::serve(listener, app).await?;
    Ok(())
}
