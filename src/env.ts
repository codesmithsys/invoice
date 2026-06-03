import { createEnv } from "@t3-oss/env-nextjs";
import { z } from "zod";

const e = (key: string) => process.env[key] ?? "";

export const env = createEnv({
  server: {
    AUTH_TOKEN: z.string(),

    RESEND_API_KEY: z.string(),

    UPSTASH_REDIS_REST_URL: z.string(),
    UPSTASH_REDIS_REST_TOKEN: z.string(),

    TELEGRAM_BOT_TOKEN: z.string(),
    TELEGRAM_CHAT_ID: z.string(),

    FROM_NAME: z.string(),
    FROM_ADDRESS: z.string(),
    FROM_VAT_NO: z.string(),
    FROM_EMAIL: z.string(),
    FROM_ACCOUNT_NUMBER: z.string(),
    FROM_SWIFT_BIC: z.string(),

    TO_NAME: z.string(),
    TO_ADDRESS: z.string(),
    TO_VAT_NO: z.string(),
    TO_EMAIL: z.string(),

    INVOICE_NET_PRICE: z.string(),
    INVOICE_EMAIL_RECIPIENT: z.string(),
    INVOICE_EMAIL_COMPANY_TO: z.string(),

    GOOGLE_DRIVE_PARENT_FOLDER_ID: z.string(),
    GOOGLE_DRIVE_CLIENT_EMAIL: z.string(),
    GOOGLE_DRIVE_PRIVATE_KEY: z.string(),

    GITHUB_TOKEN: z.string(),
  },
  client: {
    NEXT_PUBLIC_SENTRY_DSN: z.string(),
  },
  runtimeEnv: {
    NEXT_PUBLIC_SENTRY_DSN: e("NEXT_PUBLIC_SENTRY_DSN"),

    AUTH_TOKEN: e("AUTH_TOKEN"),

    RESEND_API_KEY: e("RESEND_API_KEY"),

    UPSTASH_REDIS_REST_URL: e("UPSTASH_REDIS_REST_URL"),
    UPSTASH_REDIS_REST_TOKEN: e("UPSTASH_REDIS_REST_TOKEN"),

    TELEGRAM_BOT_TOKEN: e("TELEGRAM_BOT_TOKEN"),
    TELEGRAM_CHAT_ID: e("TELEGRAM_CHAT_ID"),

    FROM_NAME: e("FROM_NAME"),
    FROM_ADDRESS: e("FROM_ADDRESS"),
    FROM_VAT_NO: e("FROM_VAT_NO"),
    FROM_EMAIL: e("FROM_EMAIL"),
    FROM_ACCOUNT_NUMBER: e("FROM_ACCOUNT_NUMBER"),
    FROM_SWIFT_BIC: e("FROM_SWIFT_BIC"),

    TO_NAME: e("TO_NAME"),
    TO_ADDRESS: e("TO_ADDRESS"),
    TO_VAT_NO: e("TO_VAT_NO"),
    TO_EMAIL: e("TO_EMAIL"),

    INVOICE_NET_PRICE: e("INVOICE_NET_PRICE"),
    INVOICE_EMAIL_RECIPIENT: e("INVOICE_EMAIL_RECIPIENT"),
    INVOICE_EMAIL_COMPANY_TO: e("INVOICE_EMAIL_COMPANY_TO"),

    GOOGLE_DRIVE_PARENT_FOLDER_ID: e("GOOGLE_DRIVE_PARENT_FOLDER_ID"),
    GOOGLE_DRIVE_CLIENT_EMAIL: e("GOOGLE_DRIVE_CLIENT_EMAIL"),
    GOOGLE_DRIVE_PRIVATE_KEY: e("GOOGLE_DRIVE_PRIVATE_KEY"),

    GITHUB_TOKEN: e("GITHUB_TOKEN"),
  },
});
