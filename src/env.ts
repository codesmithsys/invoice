import { createEnv } from "@t3-oss/env-nextjs";
import { z } from "zod";

export const env = createEnv({
  server: {
    AUTH_TOKEN: z.string().default(""),

    RESEND_API_KEY: z.string().default(""),

    UPSTASH_REDIS_REST_URL: z.string().default(""),
    UPSTASH_REDIS_REST_TOKEN: z.string().default(""),

    TELEGRAM_BOT_TOKEN: z.string().default(""),
    TELEGRAM_CHAT_ID: z.string().default(""),

    FROM_NAME: z.string().default(""),
    FROM_ADDRESS: z.string().default(""),
    FROM_VAT_NO: z.string().default(""),
    FROM_EMAIL: z.string().default(""),
    FROM_ACCOUNT_NUMBER: z.string().default(""),
    FROM_SWIFT_BIC: z.string().default(""),

    TO_NAME: z.string().default(""),
    TO_ADDRESS: z.string().default(""),
    TO_VAT_NO: z.string().default(""),
    TO_EMAIL: z.string().default(""),

    INVOICE_NET_PRICE: z.string().default(""),
    INVOICE_EMAIL_RECIPIENT: z.string().default(""),
    INVOICE_EMAIL_COMPANY_TO: z.string().default(""),

    GOOGLE_DRIVE_PARENT_FOLDER_ID: z.string().default(""),
    GOOGLE_DRIVE_CLIENT_EMAIL: z.string().default(""),
    GOOGLE_DRIVE_PRIVATE_KEY: z.string().default(""),

    GITHUB_TOKEN: z.string().default(""),
  },
  client: {
    NEXT_PUBLIC_SENTRY_DSN: z.string().default(""),
  },
  // If you're using Next.js < 13.4.4, you'll need to specify the runtimeEnv manually
  runtimeEnv: {
    NEXT_PUBLIC_SENTRY_DSN: process.env.NEXT_PUBLIC_SENTRY_DSN,

    AUTH_TOKEN: process.env.AUTH_TOKEN,

    RESEND_API_KEY: process.env.RESEND_API_KEY,

    UPSTASH_REDIS_REST_URL: process.env.UPSTASH_REDIS_REST_URL,
    UPSTASH_REDIS_REST_TOKEN: process.env.UPSTASH_REDIS_REST_TOKEN,

    TELEGRAM_BOT_TOKEN: process.env.TELEGRAM_BOT_TOKEN,
    TELEGRAM_CHAT_ID: process.env.TELEGRAM_CHAT_ID,

    FROM_NAME: process.env.FROM_NAME,
    FROM_ADDRESS: process.env.FROM_ADDRESS,
    FROM_VAT_NO: process.env.FROM_VAT_NO,
    FROM_EMAIL: process.env.FROM_EMAIL,
    FROM_ACCOUNT_NUMBER: process.env.FROM_ACCOUNT_NUMBER,
    FROM_SWIFT_BIC: process.env.FROM_SWIFT_BIC,

    TO_NAME: process.env.TO_NAME,
    TO_ADDRESS: process.env.TO_ADDRESS,
    TO_VAT_NO: process.env.TO_VAT_NO,
    TO_EMAIL: process.env.TO_EMAIL,

    INVOICE_NET_PRICE: process.env.INVOICE_NET_PRICE,
    INVOICE_EMAIL_RECIPIENT: process.env.INVOICE_EMAIL_RECIPIENT,
    INVOICE_EMAIL_COMPANY_TO: process.env.INVOICE_EMAIL_COMPANY_TO,

    GOOGLE_DRIVE_PARENT_FOLDER_ID: process.env.GOOGLE_DRIVE_PARENT_FOLDER_ID,
    GOOGLE_DRIVE_CLIENT_EMAIL: process.env.GOOGLE_DRIVE_CLIENT_EMAIL,
    GOOGLE_DRIVE_PRIVATE_KEY: process.env.GOOGLE_DRIVE_PRIVATE_KEY,

    GITHUB_TOKEN: process.env.GITHUB_TOKEN,
  },
  // emptyStringAsUndefined: true,
});
