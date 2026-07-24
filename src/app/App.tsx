import { RouterProvider } from 'react-router-dom';
import { router } from './routes';
import 'leaflet/dist/leaflet.css';
import '../styles/responsive.css';

export default function App() {
  return <RouterProvider router={router} />;
}