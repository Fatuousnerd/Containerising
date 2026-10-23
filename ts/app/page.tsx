import { addEntry, deleteEntry } from "./actions";
import { getDb, type Entry } from "@/lib/db";

export const dynamic = "force-dynamic";

export default function Home() {
  const entries = getDb()
    .prepare("SELECT id, name, message, created_at FROM entries ORDER BY id DESC")
    .all() as Entry[];

  return (
    <main>
      <h1>Guestbook</h1>
      <p className="subtitle">Leave a note for everyone who visits.</p>

      <section className="card">
        <form action={addEntry}>
          <label>
            Name
            <input name="name" maxLength={80} required />
          </label>
          <label>
            Message
            <textarea name="message" maxLength={500} required />
          </label>
          <button type="submit">Post message</button>
        </form>
      </section>

      {entries.map((entry) => (
        <article className="card entry" key={entry.id}>
          <h2>{entry.name}</h2>
          <p>{entry.message}</p>
          <time>{entry.created_at}</time>
          <form action={deleteEntry}>
            <input type="hidden" name="id" value={entry.id} />
            <button className="delete" type="submit">
              Delete
            </button>
          </form>
        </article>
      ))}

      {entries.length === 0 ? <p className="empty">No messages yet.</p> : null}
    </main>
  );
}
