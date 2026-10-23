"use server";

import { revalidatePath } from "next/cache";
import { getDb } from "@/lib/db";

export async function addEntry(formData: FormData): Promise<void> {
  const name = String(formData.get("name") ?? "").trim();
  const message = String(formData.get("message") ?? "").trim();

  if (!name || !message) {
    return;
  }

  getDb()
    .prepare("INSERT INTO entries (name, message) VALUES (?, ?)")
    .run(name, message);
  revalidatePath("/");
}

export async function deleteEntry(formData: FormData): Promise<void> {
  const id = Number(formData.get("id"));

  if (!Number.isInteger(id) || id <= 0) {
    return;
  }

  getDb().prepare("DELETE FROM entries WHERE id = ?").run(id);
  revalidatePath("/");
}
