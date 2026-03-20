import type { Metadata, Viewport } from 'next';
import localFont from 'next/font/local';
import { cn } from '@/lib/utils';
import './globals.css';

const geistSans = localFont({
  src: './fonts/GeistVF.woff',
  variable: '--font-sans',
  weight: '100 900',
});

const geistMono = localFont({
  src: './fonts/GeistMonoVF.woff',
  variable: '--font-geist-mono',
  weight: '100 900',
});

export const metadata: Metadata = {
  title: {
    default:  'Theja',
    template: '%s — Theja',
  },
  description: 'Gestionale SaaS enterprise per ottici',
  manifest: '/manifest.json',
  appleWebApp: {
    capable:    true,
    statusBarStyle: 'default',
    title:      'Theja',
  },
  formatDetection: { telephone: false },
};

export const viewport: Viewport = {
  themeColor:         '#2563eb',
  width:              'device-width',
  initialScale:       1,
  maximumScale:       1,
  userScalable:       false,
  viewportFit:        'cover',
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="it" className={cn('font-sans', geistSans.variable, geistMono.variable)}>
      <body className="antialiased bg-background text-foreground">
        {children}
      </body>
    </html>
  );
}
