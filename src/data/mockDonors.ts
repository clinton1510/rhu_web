import { DONOR_LOCATIONS } from './batangasLocations';

export interface Donor {
  id: string;
  name: string;
  bloodType: string;
  location: {
    lat: number;
    lng: number;
    address: string;
    municipality: string;
    barangay?: string;
  };
  phone: string;
  email: string;
  lastDonation: string;
  totalDonations: number;
  responseRate: number;
  reliability: 'high' | 'medium' | 'low';
  clusterSegment: string;
}

const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const firstNames = ['Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Rosa', 'Carlos', 'Elena', 'Miguel', 'Sofia', 'Ricardo', 'Isabel', 'Antonio', 'Carmen', 'Luis', 'Patricia'];
const lastNames = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Ramos', 'Flores', 'Rivera', 'Gonzales', 'Pascual', 'Aquino'];

function getRandomItem<T>(array: T[]): T {
  return array[Math.floor(Math.random() * array.length)];
}

function getRandomDate(daysAgo: number): string {
  const date = new Date();
  date.setDate(date.getDate() - daysAgo);
  return date.toISOString().split('T')[0];
}

// Generate realistic donors for Batangas District 1
export const MOCK_DONORS: Donor[] = DONOR_LOCATIONS.flatMap((location, locationIndex) => {
  // Generate 2-4 donors per location
  const numDonors = Math.floor(Math.random() * 3) + 2;
  
  return Array.from({ length: numDonors }, (_, i) => {
    const donorId = `${locationIndex}-${i}`;
    const name = `${getRandomItem(firstNames)} ${getRandomItem(lastNames)}`;
    const bloodType = getRandomItem(bloodTypes);
    const totalDonations = Math.floor(Math.random() * 20) + 1;
    const responseRate = Math.floor(Math.random() * 40) + 60; // 60-100%
    
    // Add small random offset to coordinates (within ~500m)
    const latOffset = (Math.random() - 0.5) * 0.008;
    const lngOffset = (Math.random() - 0.5) * 0.008;
    
    return {
      id: `donor-${donorId}`,
      name,
      bloodType,
      location: {
        lat: location.lat + latOffset,
        lng: location.lng + lngOffset,
        address: location.address,
        municipality: location.municipality,
        barangay: location.barangay
      },
      phone: `+639${Math.floor(Math.random() * 900000000 + 100000000)}`,
      email: `${name.toLowerCase().replace(' ', '.')}@email.com`,
      lastDonation: getRandomDate(Math.floor(Math.random() * 120) + 30), // 30-150 days ago
      totalDonations,
      responseRate,
      reliability: responseRate >= 85 ? 'high' : responseRate >= 70 ? 'medium' : 'low',
      clusterSegment: totalDonations >= 10 ? 'Highly Active' : totalDonations >= 5 ? 'Regular' : 'Occasional'
    };
  });
});

// Get donors by blood type compatibility
export function getDonorsByBloodType(requiredBloodType: string, donors: Donor[] = MOCK_DONORS): Donor[] {
  // Blood type compatibility matrix (who can donate to whom)
  const compatibility: Record<string, string[]> = {
    'O-': ['O-'],
    'O+': ['O-', 'O+'],
    'A-': ['O-', 'A-'],
    'A+': ['O-', 'O+', 'A-', 'A+'],
    'B-': ['O-', 'B-'],
    'B+': ['O-', 'O+', 'B-', 'B+'],
    'AB-': ['O-', 'A-', 'B-', 'AB-'],
    'AB+': ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+']
  };
  
  const compatibleTypes = compatibility[requiredBloodType] || [];
  return donors.filter(donor => compatibleTypes.includes(donor.bloodType));
}

// Get donors within a certain radius (in kilometers)
export function getDonorsWithinRadius(
  centerLat: number, 
  centerLng: number, 
  radiusKm: number,
  donors: Donor[] = MOCK_DONORS
): Donor[] {
  return donors.filter(donor => {
    const distance = calculateDistance(
      centerLat,
      centerLng,
      donor.location.lat,
      donor.location.lng
    );
    return distance <= radiusKm;
  });
}

function calculateDistance(lat1: number, lng1: number, lat2: number, lng2: number): number {
  const R = 6371; // Earth's radius in km
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  
  const a = 
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
    Math.sin(dLng / 2) * Math.sin(dLng / 2);
  
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}

function toRad(degrees: number): number {
  return degrees * (Math.PI / 180);
}
