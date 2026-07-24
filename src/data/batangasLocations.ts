/**
 * Real locations and coordinates for Batangas District 1
 * Batangas District 1 includes:
 * - Batangas City
 * - Bauan
 * - San Pascual
 * - Mabini
 * - Tingloy
 * - San Luis
 * - Lobo
 * - Taysan
 */

export interface Location {
  lat: number;
  lng: number;
  name: string;
  address: string;
  municipality: string;
  barangay?: string;
}

// Hospital Locations in Batangas District 1
export const HOSPITALS: Record<string, Location> = {
  'batangas-medical-center': {
    lat: 13.7565,
    lng: 121.0583,
    name: 'Batangas Medical Center',
    address: 'Kumintang Ibaba, Batangas City',
    municipality: 'Batangas City',
    barangay: 'Kumintang Ibaba'
  },
  'mary-mediatrix-medical-center': {
    lat: 13.7610,
    lng: 121.0530,
    name: 'Mary Mediatrix Medical Center',
    address: 'P. Burgos St., Batangas City',
    municipality: 'Batangas City',
    barangay: 'Poblacion'
  },
  'bauan-doctors-hospital': {
    lat: 13.7920,
    lng: 121.0090,
    name: 'Bauan Doctors Hospital',
    address: 'National Highway, Bauan',
    municipality: 'Bauan',
    barangay: 'Poblacion'
  },
  'st-bridget-medical-center': {
    lat: 13.7580,
    lng: 121.0600,
    name: 'St. Bridget Medical Center',
    address: 'D. Silang Street, Batangas City',
    municipality: 'Batangas City',
    barangay: 'Poblacion'
  },
  'san-pascual-health-center': {
    lat: 13.8090,
    lng: 121.0270,
    name: 'San Pascual District Hospital',
    address: 'San Pascual, Batangas',
    municipality: 'San Pascual',
    barangay: 'Poblacion'
  }
};

// Sample Donor Locations in Batangas District 1
export const DONOR_LOCATIONS: Location[] = [
  // Batangas City
  {
    lat: 13.7565,
    lng: 121.0640,
    name: 'Poblacion Area',
    address: 'P. Burgos St., Batangas City',
    municipality: 'Batangas City',
    barangay: 'Poblacion'
  },
  {
    lat: 13.7500,
    lng: 121.0550,
    name: 'Kumintang Ibaba',
    address: 'Kumintang Ibaba, Batangas City',
    municipality: 'Batangas City',
    barangay: 'Kumintang Ibaba'
  },
  {
    lat: 13.7620,
    lng: 121.0490,
    name: 'Alangilan',
    address: 'Alangilan, Batangas City',
    municipality: 'Batangas City',
    barangay: 'Alangilan'
  },
  {
    lat: 13.7700,
    lng: 121.0450,
    name: 'Pallocan West',
    address: 'Pallocan West, Batangas City',
    municipality: 'Batangas City',
    barangay: 'Pallocan West'
  },
  {
    lat: 13.7430,
    lng: 121.0620,
    name: 'Libjo',
    address: 'Libjo, Batangas City',
    municipality: 'Batangas City',
    barangay: 'Libjo'
  },
  
  // Bauan
  {
    lat: 13.7920,
    lng: 121.0110,
    name: 'Bauan Poblacion',
    address: 'Poblacion, Bauan',
    municipality: 'Bauan',
    barangay: 'Poblacion'
  },
  {
    lat: 13.8000,
    lng: 121.0050,
    name: 'San Diego, Bauan',
    address: 'San Diego, Bauan',
    municipality: 'Bauan',
    barangay: 'San Diego'
  },
  {
    lat: 13.7850,
    lng: 121.0180,
    name: 'Mabacong, Bauan',
    address: 'Mabacong, Bauan',
    municipality: 'Bauan',
    barangay: 'Mabacong'
  },
  
  // San Pascual
  {
    lat: 13.8090,
    lng: 121.0300,
    name: 'San Pascual Centro',
    address: 'Poblacion, San Pascual',
    municipality: 'San Pascual',
    barangay: 'Poblacion'
  },
  {
    lat: 13.8150,
    lng: 121.0250,
    name: 'Aya, San Pascual',
    address: 'Aya, San Pascual',
    municipality: 'San Pascual',
    barangay: 'Aya'
  },
  {
    lat: 13.8020,
    lng: 121.0350,
    name: 'Sico 1, San Pascual',
    address: 'Sico 1, San Pascual',
    municipality: 'San Pascual',
    barangay: 'Sico 1'
  },
  
  // Mabini
  {
    lat: 13.7280,
    lng: 120.9050,
    name: 'Anilao, Mabini',
    address: 'Anilao, Mabini',
    municipality: 'Mabini',
    barangay: 'Anilao'
  },
  {
    lat: 13.7420,
    lng: 120.9180,
    name: 'Poblacion, Mabini',
    address: 'Poblacion, Mabini',
    municipality: 'Mabini',
    barangay: 'Poblacion'
  },
  
  // San Luis
  {
    lat: 13.7750,
    lng: 121.0780,
    name: 'San Luis Poblacion',
    address: 'Poblacion, San Luis',
    municipality: 'San Luis',
    barangay: 'Poblacion'
  },
  {
    lat: 13.7680,
    lng: 121.0850,
    name: 'Durungao, San Luis',
    address: 'Durungao, San Luis',
    municipality: 'San Luis',
    barangay: 'Durungao'
  },
  
  // Lobo
  {
    lat: 13.6580,
    lng: 121.2450,
    name: 'Lobo Poblacion',
    address: 'Poblacion, Lobo',
    municipality: 'Lobo',
    barangay: 'Poblacion'
  },
  
  // Taysan
  {
    lat: 13.7700,
    lng: 121.2120,
    name: 'Taysan Poblacion',
    address: 'Poblacion, Taysan',
    municipality: 'Taysan',
    barangay: 'Poblacion'
  }
];

// Calculate distance between two coordinates using Haversine formula
export function calculateDistance(lat1: number, lng1: number, lat2: number, lng2: number): number {
  const R = 6371; // Radius of the Earth in kilometers
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  
  const a = 
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
    Math.sin(dLng / 2) * Math.sin(dLng / 2);
  
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  const distance = R * c;
  
  return distance;
}

function toRad(degrees: number): number {
  return degrees * (Math.PI / 180);
}

// Calculate estimated travel time based on distance (rough approximation)
// Assumes average speed of 30 km/h in city, 40 km/h on highway
export function calculateETA(distanceKm: number): number {
  const avgSpeedKmh = distanceKm < 10 ? 30 : 40;
  const hours = distanceKm / avgSpeedKmh;
  return Math.round(hours * 60); // Return minutes
}

// Get the center of Batangas District 1 (Batangas City center)
export const BATANGAS_CENTER: Location = {
  lat: 13.7565,
  lng: 121.0583,
  name: 'Batangas City Center',
  address: 'Batangas City',
  municipality: 'Batangas City'
};

// Blood type compatibility matrix
export const BLOOD_COMPATIBILITY: Record<string, string[]> = {
  'O-': ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
  'O+': ['O+', 'A+', 'B+', 'AB+'],
  'A-': ['A-', 'A+', 'AB-', 'AB+'],
  'A+': ['A+', 'AB+'],
  'B-': ['B-', 'B+', 'AB-', 'AB+'],
  'B+': ['B+', 'AB+'],
  'AB-': ['AB-', 'AB+'],
  'AB+': ['AB+']
};

export function canDonate(donorBloodType: string, requiredBloodType: string): boolean {
  const compatibleRecipients = BLOOD_COMPATIBILITY[donorBloodType] || [];
  return compatibleRecipients.includes(requiredBloodType);
}
