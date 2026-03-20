import { redirect } from 'next/navigation';

/**
 * Root page: reindirizza sempre alla dashboard.
 * Il layout della dashboard verificherà il token e reindirizzerà al login se necessario.
 */
export default function RootPage() {
  redirect('/dashboard');
}
