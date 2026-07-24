import { Donor, BloodRequest, DemandForecast, DonorBehaviorCluster, BloodType, BloodBank } from '../types';

// Mock donors with behavioral profiling
export const mockDonors: Donor[] = [
  {
    id: 'D001',
    name: 'John Michael Santos',
    bloodType: 'O+',
    location: { lat: 14.5995, lng: 120.9842, address: 'Manila City Hall, Manila' },
    availability: true,
    donationHistory: 12,
    lastDonation: new Date('2025-12-15'),
    responseRate: 95,
    avgResponseTime: 8,
    cluster: 'reliable',
    contactNumber: '09171234567',
    email: 'john.santos@email.com',
    verified: true,
  },
  {
    id: 'D002',
    name: 'Maria Clara Reyes',
    bloodType: 'A+',
    location: { lat: 14.6091, lng: 121.0223, address: 'Makati Medical Center Area' },
    availability: true,
    donationHistory: 8,
    lastDonation: new Date('2026-01-20'),
    responseRate: 88,
    avgResponseTime: 12,
    cluster: 'reliable',
    contactNumber: '09187654321',
    email: 'maria.reyes@email.com',
    verified: true,
  },
  {
    id: 'D003',
    name: 'Carlos Dela Cruz',
    bloodType: 'O-',
    location: { lat: 14.5764, lng: 121.0851, address: 'Pasig City' },
    availability: true,
    donationHistory: 15,
    lastDonation: new Date('2025-11-10'),
    responseRate: 92,
    avgResponseTime: 10,
    cluster: 'reliable',
    contactNumber: '09261234567',
    email: 'carlos.dc@email.com',
    verified: true,
  },
  {
    id: 'D004',
    name: 'Anna Marie Garcia',
    bloodType: 'B+',
    location: { lat: 14.6507, lng: 121.0494, address: 'Quezon City Hall' },
    availability: false,
    donationHistory: 5,
    lastDonation: new Date('2026-02-28'),
    responseRate: 75,
    avgResponseTime: 18,
    cluster: 'moderate',
    contactNumber: '09351234567',
    email: 'anna.garcia@email.com',
    verified: true,
  },
  {
    id: 'D005',
    name: 'Roberto Tan',
    bloodType: 'AB+',
    location: { lat: 14.5547, lng: 121.0244, address: 'Taguig City' },
    availability: true,
    donationHistory: 3,
    lastDonation: new Date('2026-03-01'),
    responseRate: 67,
    avgResponseTime: 25,
    cluster: 'moderate',
    contactNumber: '09451234567',
    email: 'roberto.tan@email.com',
    verified: true,
  },
  {
    id: 'D006',
    name: 'Sofia Lim',
    bloodType: 'O+',
    location: { lat: 14.6760, lng: 121.0437, address: 'Fairview, Quezon City' },
    availability: true,
    donationHistory: 1,
    lastDonation: new Date('2026-02-15'),
    responseRate: 100,
    avgResponseTime: 15,
    cluster: 'new',
    contactNumber: '09561234567',
    email: 'sofia.lim@email.com',
    verified: true,
  },
  {
    id: 'D007',
    name: 'Miguel Rodriguez',
    bloodType: 'A-',
    location: { lat: 14.5378, lng: 121.0199, address: 'Parañaque City' },
    availability: true,
    donationHistory: 7,
    lastDonation: new Date('2025-12-28'),
    responseRate: 85,
    avgResponseTime: 11,
    cluster: 'reliable',
    contactNumber: '09671234567',
    email: 'miguel.rod@email.com',
    verified: true,
  },
  {
    id: 'D008',
    name: 'Patricia Cruz',
    bloodType: 'B-',
    location: { lat: 14.5243, lng: 121.0198, address: 'Las Piñas City' },
    availability: true,
    donationHistory: 10,
    lastDonation: new Date('2026-01-05'),
    responseRate: 90,
    avgResponseTime: 9,
    cluster: 'reliable',
    contactNumber: '09781234567',
    email: 'patricia.cruz@email.com',
    verified: true,
  },
];

// Mock blood requests
export const mockBloodRequests: BloodRequest[] = [
  {
    id: 'R001',
    hospitalId: 'H001',
    hospitalName: 'Philippine General Hospital',
    bloodType: 'O+',
    quantity: 2,
    urgency: 'critical',
    location: { lat: 14.5794, lng: 120.9895, address: 'Taft Avenue, Manila' },
    requestedAt: new Date('2026-03-19T10:30:00'),
    neededBy: new Date('2026-03-19T14:00:00'),
    status: 'matching',
    patientInfo: 'Traffic accident victim, urgent surgery',
  },
  {
    id: 'R002',
    hospitalId: 'H002',
    hospitalName: 'Makati Medical Center',
    bloodType: 'A+',
    quantity: 3,
    urgency: 'urgent',
    location: { lat: 14.5547, lng: 121.0244, address: 'Amorsolo St, Makati' },
    requestedAt: new Date('2026-03-19T09:15:00'),
    neededBy: new Date('2026-03-19T18:00:00'),
    status: 'pending',
    patientInfo: 'Scheduled surgery',
  },
  {
    id: 'R003',
    hospitalId: 'H003',
    hospitalName: 'St. Luke\'s Medical Center',
    bloodType: 'O-',
    quantity: 1,
    urgency: 'critical',
    location: { lat: 14.6507, lng: 121.0494, address: 'E. Rodriguez Sr. Ave, QC' },
    requestedAt: new Date('2026-03-19T11:00:00'),
    neededBy: new Date('2026-03-19T13:00:00'),
    status: 'matching',
    patientInfo: 'Emergency childbirth complications',
  },
];

// Mock demand forecast data (Prophet/ARIMA simulation)
export const mockDemandForecast: DemandForecast[] = [
  { date: '2026-03-20', predicted: 45, actual: 42, bloodType: 'O+', confidence: 0.92 },
  { date: '2026-03-21', predicted: 38, actual: 40, bloodType: 'O+', confidence: 0.89 },
  { date: '2026-03-22', predicted: 52, actual: 49, bloodType: 'O+', confidence: 0.91 },
  { date: '2026-03-23', predicted: 48, bloodType: 'O+', confidence: 0.88 },
  { date: '2026-03-24', predicted: 41, bloodType: 'O+', confidence: 0.87 },
  { date: '2026-03-25', predicted: 39, bloodType: 'O+', confidence: 0.85 },
  { date: '2026-03-26', predicted: 44, bloodType: 'O+', confidence: 0.86 },
  { date: '2026-03-27', predicted: 58, bloodType: 'O+', confidence: 0.84 },
  { date: '2026-03-28', predicted: 63, bloodType: 'O+', confidence: 0.82 },
  { date: '2026-03-29', predicted: 55, bloodType: 'O+', confidence: 0.83 },
];

// Mock donor clustering data (K-Means simulation)
export const mockDonorClusters: DonorBehaviorCluster[] = [
  {
    cluster: 'reliable',
    count: 5,
    avgResponseTime: 10,
    avgResponseRate: 90,
    characteristics: [
      'Consistent donation history (7+ donations)',
      'Response time under 12 minutes',
      'High availability rate',
      'Verified contact information',
    ],
  },
  {
    cluster: 'moderate',
    count: 2,
    avgResponseTime: 21.5,
    avgResponseRate: 71,
    characteristics: [
      'Moderate donation history (3-6 donations)',
      'Response time 15-30 minutes',
      'Occasional availability issues',
      'Verified profiles',
    ],
  },
  {
    cluster: 'new',
    count: 1,
    avgResponseTime: 15,
    avgResponseRate: 100,
    characteristics: [
      'New donors (1-2 donations)',
      'Limited historical data',
      'High initial engagement',
      'Potential for reliable cluster',
    ],
  },
];

// Simulate ML prediction for donor response probability
export function predictResponseProbability(donor: Donor, urgency: UrgencyLevel): number {
  let baseProbability = donor.responseRate;
  
  // Adjust based on urgency
  if (urgency === 'critical') baseProbability += 5;
  if (urgency === 'scheduled') baseProbability -= 5;
  
  // Adjust based on availability
  if (!donor.availability) baseProbability -= 30;
  
  // Adjust based on cluster
  if (donor.cluster === 'reliable') baseProbability += 3;
  if (donor.cluster === 'new') baseProbability -= 2;
  
  // Adjust based on recent donation
  if (donor.lastDonation) {
    const daysSince = Math.floor((Date.now() - donor.lastDonation.getTime()) / (1000 * 60 * 60 * 24));
    if (daysSince < 56) baseProbability -= 15; // Too soon (8 weeks rule)
  }
  
  return Math.max(0, Math.min(100, baseProbability));
}

// Calculate haversine distance between two points
export function calculateDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const R = 6371; // Earth's radius in km
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = 
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
    Math.sin(dLon / 2) * Math.sin(dLon / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}

// Simulate ETA calculation (OSRM-like)
export function calculateETA(distance: number): number {
  const avgSpeed = 25; // km/h in urban traffic
  const baseTime = (distance / avgSpeed) * 60; // minutes
  const bufferTime = Math.random() * 5; // random buffer 0-5 min
  return Math.round(baseTime + bufferTime);
}

// Generate mock route path (simplified)
export function generateRoutePath(
  startLat: number,
  startLng: number,
  endLat: number,
  endLng: number
): Array<[number, number]> {
  const points: Array<[number, number]> = [];
  const steps = 10;
  
  for (let i = 0; i <= steps; i++) {
    const ratio = i / steps;
    const lat = startLat + (endLat - startLat) * ratio;
    const lng = startLng + (endLng - startLng) * ratio;
    points.push([lat, lng]);
  }
  
  return points;
}

// Mock blood banks in Metro Manila
export const mockBloodBanks: BloodBank[] = [
  {
    id: 'BB001',
    name: 'Philippine Red Cross - Manila Chapter',
    location: {
      lat: 14.5995,
      lng: 120.9842,
      address: '37 EDSA corner Boni Ave, Mandaluyong City'
    },
    contactNumber: '(02) 8790-2300',
    email: 'manila@redcross.org.ph',
    operatingHours: {
      weekday: '8:00 AM - 5:00 PM',
      weekend: '8:00 AM - 12:00 PM'
    },
    services: [
      'Blood Donation',
      'Blood Typing',
      'Component Separation',
      'Emergency Blood Supply',
      'Platelet Donation'
    ],
    currentStock: [
      { bloodType: 'O+', units: 45, status: 'good' },
      { bloodType: 'O-', units: 8, status: 'low' },
      { bloodType: 'A+', units: 32, status: 'adequate' },
      { bloodType: 'A-', units: 5, status: 'critical' },
      { bloodType: 'B+', units: 28, status: 'adequate' },
      { bloodType: 'B-', units: 6, status: 'low' },
      { bloodType: 'AB+', units: 15, status: 'adequate' },
      { bloodType: 'AB-', units: 3, status: 'critical' }
    ],
    verified: true,
    rating: 4.8
  },
  {
    id: 'BB002',
    name: 'Philippine General Hospital Blood Bank',
    location: {
      lat: 14.5794,
      lng: 120.9895,
      address: 'Taft Avenue, Ermita, Manila'
    },
    contactNumber: '(02) 8554-8400',
    email: 'bloodbank@pgh.gov.ph',
    operatingHours: {
      weekday: '7:00 AM - 6:00 PM',
      weekend: '8:00 AM - 2:00 PM'
    },
    services: [
      'Blood Donation',
      'Blood Screening',
      'Blood Component Therapy',
      'Emergency Services',
      'Autologous Donation'
    ],
    currentStock: [
      { bloodType: 'O+', units: 52, status: 'good' },
      { bloodType: 'O-', units: 12, status: 'adequate' },
      { bloodType: 'A+', units: 38, status: 'good' },
      { bloodType: 'A-', units: 7, status: 'low' },
      { bloodType: 'B+', units: 25, status: 'adequate' },
      { bloodType: 'B-', units: 4, status: 'critical' },
      { bloodType: 'AB+', units: 18, status: 'adequate' },
      { bloodType: 'AB-', units: 5, status: 'low' }
    ],
    verified: true,
    rating: 4.7
  },
  {
    id: 'BB003',
    name: 'Makati Medical Center Blood Bank',
    location: {
      lat: 14.5547,
      lng: 121.0244,
      address: '2 Amorsolo Street, Legaspi Village, Makati City'
    },
    contactNumber: '(02) 8888-8999',
    email: 'bloodservices@makatimed.net.ph',
    operatingHours: {
      weekday: '8:00 AM - 5:00 PM',
      weekend: '9:00 AM - 1:00 PM'
    },
    services: [
      'Whole Blood Donation',
      'Apheresis',
      'Blood Crossmatching',
      'Emergency Blood Services',
      'Donor Recruitment Programs'
    ],
    currentStock: [
      { bloodType: 'O+', units: 40, status: 'good' },
      { bloodType: 'O-', units: 10, status: 'adequate' },
      { bloodType: 'A+', units: 35, status: 'good' },
      { bloodType: 'A-', units: 6, status: 'low' },
      { bloodType: 'B+', units: 30, status: 'adequate' },
      { bloodType: 'B-', units: 8, status: 'low' },
      { bloodType: 'AB+', units: 20, status: 'adequate' },
      { bloodType: 'AB-', units: 4, status: 'critical' }
    ],
    verified: true,
    rating: 4.9
  },
  {
    id: 'BB004',
    name: 'St. Luke\'s Medical Center Blood Bank - QC',
    location: {
      lat: 14.6507,
      lng: 121.0494,
      address: '279 E. Rodriguez Sr. Ave, Quezon City'
    },
    contactNumber: '(02) 8723-0301',
    email: 'bloodbank.qc@stluke.com.ph',
    operatingHours: {
      weekday: '7:30 AM - 5:30 PM',
      weekend: '8:00 AM - 3:00 PM'
    },
    services: [
      'Blood Donation',
      'Therapeutic Apheresis',
      'Stem Cell Collection',
      'Blood Component Preparation',
      'Specialized Testing'
    ],
    currentStock: [
      { bloodType: 'O+', units: 48, status: 'good' },
      { bloodType: 'O-', units: 15, status: 'adequate' },
      { bloodType: 'A+', units: 42, status: 'good' },
      { bloodType: 'A-', units: 9, status: 'low' },
      { bloodType: 'B+', units: 33, status: 'adequate' },
      { bloodType: 'B-', units: 7, status: 'low' },
      { bloodType: 'AB+', units: 22, status: 'adequate' },
      { bloodType: 'AB-', units: 6, status: 'low' }
    ],
    verified: true,
    rating: 4.9
  },
  {
    id: 'BB005',
    name: 'The Medical City Blood Bank',
    location: {
      lat: 14.5764,
      lng: 121.0851,
      address: 'Ortigas Avenue, Pasig City'
    },
    contactNumber: '(02) 8988-1000',
    email: 'bloodbank@themedicalcity.com',
    operatingHours: {
      weekday: '8:00 AM - 5:00 PM',
      weekend: '9:00 AM - 2:00 PM'
    },
    services: [
      'Blood Donation',
      'Apheresis Services',
      'Blood Component Therapy',
      'Pre-Deposit Autologous Donation',
      'Mobile Blood Donation'
    ],
    currentStock: [
      { bloodType: 'O+', units: 38, status: 'adequate' },
      { bloodType: 'O-', units: 6, status: 'low' },
      { bloodType: 'A+', units: 30, status: 'adequate' },
      { bloodType: 'A-', units: 4, status: 'critical' },
      { bloodType: 'B+', units: 26, status: 'adequate' },
      { bloodType: 'B-', units: 5, status: 'low' },
      { bloodType: 'AB+', units: 16, status: 'adequate' },
      { bloodType: 'AB-', units: 3, status: 'critical' }
    ],
    verified: true,
    rating: 4.6
  },
  {
    id: 'BB006',
    name: 'Philippine Red Cross - Quezon City Chapter',
    location: {
      lat: 14.6760,
      lng: 121.0437,
      address: '20 EDSA corner Timog Avenue, Quezon City'
    },
    contactNumber: '(02) 8927-0000',
    email: 'qc@redcross.org.ph',
    operatingHours: {
      weekday: '8:00 AM - 5:00 PM',
      weekend: '8:00 AM - 12:00 PM'
    },
    services: [
      'Blood Donation',
      'Platelet Apheresis',
      'Blood Screening',
      'Community Blood Drives',
      'Donor Education'
    ],
    currentStock: [
      { bloodType: 'O+', units: 50, status: 'good' },
      { bloodType: 'O-', units: 11, status: 'adequate' },
      { bloodType: 'A+', units: 36, status: 'adequate' },
      { bloodType: 'A-', units: 8, status: 'low' },
      { bloodType: 'B+', units: 29, status: 'adequate' },
      { bloodType: 'B-', units: 9, status: 'low' },
      { bloodType: 'AB+', units: 19, status: 'adequate' },
      { bloodType: 'AB-', units: 5, status: 'low' }
    ],
    verified: true,
    rating: 4.7
  },
  {
    id: 'BB007',
    name: 'Asian Hospital and Medical Center Blood Bank',
    location: {
      lat: 14.5243,
      lng: 121.0198,
      address: '2205 Civic Drive, Filinvest Corporate City, Alabang, Muntinlupa'
    },
    contactNumber: '(02) 8771-9000',
    email: 'bloodbank@asianhospital.com',
    operatingHours: {
      weekday: '8:00 AM - 5:00 PM',
      weekend: 'By Appointment Only'
    },
    services: [
      'Blood Donation',
      'Blood Typing',
      'Crossmatching',
      'Component Separation',
      'Pre-Surgical Donation'
    ],
    currentStock: [
      { bloodType: 'O+', units: 35, status: 'adequate' },
      { bloodType: 'O-', units: 7, status: 'low' },
      { bloodType: 'A+', units: 28, status: 'adequate' },
      { bloodType: 'A-', units: 5, status: 'critical' },
      { bloodType: 'B+', units: 24, status: 'adequate' },
      { bloodType: 'B-', units: 4, status: 'critical' },
      { bloodType: 'AB+', units: 14, status: 'adequate' },
      { bloodType: 'AB-', units: 2, status: 'critical' }
    ],
    verified: true,
    rating: 4.5
  },
  {
    id: 'BB008',
    name: 'Veterans Memorial Medical Center Blood Bank',
    location: {
      lat: 14.6091,
      lng: 121.0223,
      address: 'North Avenue, Quezon City'
    },
    contactNumber: '(02) 8927-6426',
    email: 'vmmc.bloodbank@yahoo.com',
    operatingHours: {
      weekday: '7:00 AM - 4:00 PM',
      weekend: '8:00 AM - 12:00 PM'
    },
    services: [
      'Blood Donation',
      'Blood Component Services',
      'Emergency Blood Supply',
      'Blood Screening',
      'Donor Registration'
    ],
    currentStock: [
      { bloodType: 'O+', units: 42, status: 'good' },
      { bloodType: 'O-', units: 9, status: 'low' },
      { bloodType: 'A+', units: 31, status: 'adequate' },
      { bloodType: 'A-', units: 6, status: 'low' },
      { bloodType: 'B+', units: 27, status: 'adequate' },
      { bloodType: 'B-', units: 6, status: 'low' },
      { bloodType: 'AB+', units: 17, status: 'adequate' },
      { bloodType: 'AB-', units: 4, status: 'critical' }
    ],
    verified: true,
    rating: 4.4
  }
];

// ============================================================
// RHU-SPECIFIC MOCK DATA
// ============================================================

// OPD Consultation Records
export const mockOPDConsultations = [
  { id: 'OPD001', date: '2026-06-10', patientName: 'Lourdes Bautista', age: 42, gender: 'Female', barangay: 'Halang', chiefComplaint: 'Hypertension follow-up', diagnosis: 'Essential Hypertension Stage 2', icd10: 'I10', physician: 'Dr. Maria C. Santos', disposition: 'prescribed', philhealthCharged: true, medications: ['Amlodipine 5mg', 'Losartan 50mg'], vitals: { bp: '150/90', temp: '36.8', weight: '68kg', rr: '18', hr: '78' } },
  { id: 'OPD002', date: '2026-06-10', patientName: 'Ricardo Dimayuga', age: 65, gender: 'Male', barangay: 'Mabini', chiefComplaint: 'Chest pain and shortness of breath', diagnosis: 'Acute Coronary Syndrome (r/o)', icd10: 'I20.0', physician: 'Dr. Maria C. Santos', disposition: 'referred', philhealthCharged: true, medications: ['Aspirin 80mg', 'Nitroglycerin'], vitals: { bp: '160/100', temp: '37.0', weight: '72kg', rr: '22', hr: '95' } },
  { id: 'OPD003', date: '2026-06-10', patientName: 'Cristina Magpayo', age: 28, gender: 'Female', barangay: 'San Jose', chiefComplaint: 'Prenatal check-up', diagnosis: 'Normal pregnancy, 28 weeks AOG', icd10: 'Z34.2', physician: 'Midwife Rosario Peralta', disposition: 'prescribed', philhealthCharged: false, medications: ['Ferrous Sulfate', 'Folic Acid', 'Calcium Carbonate'], vitals: { bp: '110/70', temp: '36.5', weight: '62kg', rr: '16', hr: '82' } },
  { id: 'OPD004', date: '2026-06-10', patientName: 'Alfonso Nakpil', age: 8, gender: 'Male', barangay: 'Poblacion', chiefComplaint: 'Fever and cough for 3 days', diagnosis: 'Upper Respiratory Tract Infection', icd10: 'J06.9', physician: 'Dr. Maria C. Santos', disposition: 'prescribed', philhealthCharged: false, medications: ['Paracetamol syrup', 'Amoxicillin syrup', 'Salbutamol MDI'], vitals: { bp: '90/60', temp: '38.5', weight: '22kg', rr: '28', hr: '105' } },
  { id: 'OPD005', date: '2026-06-09', patientName: 'Natividad Soriano', age: 55, gender: 'Female', barangay: 'Halang', chiefComplaint: 'Diabetes management', diagnosis: 'Type 2 Diabetes Mellitus (uncontrolled)', icd10: 'E11.9', physician: 'Dr. Maria C. Santos', disposition: 'prescribed', philhealthCharged: true, medications: ['Metformin 500mg', 'Glibenclamide 5mg'], vitals: { bp: '130/80', temp: '36.7', weight: '75kg', rr: '18', hr: '76' } },
  { id: 'OPD006', date: '2026-06-09', patientName: 'Eduardo Villanueva', age: 34, gender: 'Male', barangay: 'Mabini', chiefComplaint: 'Wound care — laceration right hand', diagnosis: 'Laceration, right hand', icd10: 'S61.4', physician: 'Dr. Joseph T. Ramos', disposition: 'prescribed', philhealthCharged: false, medications: ['Amoxicillin 500mg', 'Mefenamic Acid'], vitals: { bp: '120/80', temp: '36.9', weight: '70kg', rr: '17', hr: '80' } },
  { id: 'OPD007', date: '2026-06-09', patientName: 'Maricel Sta. Cruz', age: 19, gender: 'Female', barangay: 'San Jose', chiefComplaint: 'UTI symptoms', diagnosis: 'Urinary Tract Infection', icd10: 'N39.0', physician: 'Dr. Maria C. Santos', disposition: 'prescribed', philhealthCharged: false, medications: ['Co-trimoxazole', 'Phenazopyridine'], vitals: { bp: '110/70', temp: '37.4', weight: '52kg', rr: '16', hr: '84' } },
  { id: 'OPD008', date: '2026-06-08', patientName: 'Felicitas Aguilar', age: 71, gender: 'Female', barangay: 'Kumintang Ilaya', chiefComplaint: 'Joint pain (bilateral knees)', diagnosis: 'Osteoarthritis, bilateral knees', icd10: 'M17.0', physician: 'Dr. Joseph T. Ramos', disposition: 'prescribed', philhealthCharged: true, medications: ['Celecoxib 200mg', 'Calcium + Vit D'], vitals: { bp: '140/85', temp: '36.6', weight: '60kg', rr: '17', hr: '74' } },
];

// Immunization Records
export const mockImmunizations = [
  { id: 'IMM001', childName: 'Baby Boy Reyes', motherName: 'Ana Reyes', barangay: 'Halang', dob: '2026-01-15', age: '5 months', vaccines: [{ name: 'BCG', date: '2026-01-15', lot: 'BCG-2024-001', status: 'given' }, { name: 'HepB (birth)', date: '2026-01-15', lot: 'HepB-001', status: 'given' }, { name: 'DPT-HepB-Hib 1', date: '2026-03-15', lot: 'Penta-002', status: 'given' }, { name: 'OPV 1', date: '2026-03-15', lot: 'OPV-003', status: 'given' }, { name: 'PCV 1', date: '2026-03-15', lot: 'PCV-001', status: 'given' }, { name: 'DPT-HepB-Hib 2', date: '2026-05-15', lot: 'Penta-005', status: 'given' }, { name: 'OPV 2', date: '2026-05-15', lot: 'OPV-006', status: 'given' }, { name: 'PCV 2', date: '2026-05-15', lot: 'PCV-003', status: 'given' }], nextVisit: '2026-07-15', status: 'on_schedule', bhw: 'Natividad Puno' },
  { id: 'IMM002', childName: 'Baby Girl Mercado', motherName: 'Roselyn Mercado', barangay: 'Mabini', dob: '2025-12-20', age: '6 months', vaccines: [{ name: 'BCG', date: '2025-12-20', lot: 'BCG-2024-001', status: 'given' }, { name: 'HepB (birth)', date: '2025-12-20', lot: 'HepB-002', status: 'given' }, { name: 'DPT-HepB-Hib 1', date: '2026-02-20', lot: 'Penta-003', status: 'given' }, { name: 'OPV 1', date: '2026-02-20', lot: 'OPV-004', status: 'given' }, { name: 'PCV 1', date: '2026-02-20', lot: 'PCV-002', status: 'given' }, { name: 'DPT-HepB-Hib 2', date: '2026-04-20', lot: 'Penta-006', status: 'given' }, { name: 'OPV 2', date: '2026-04-20', lot: 'OPV-007', status: 'given' }, { name: 'DPT-HepB-Hib 3', date: '2026-06-15', lot: '', status: 'due' }], nextVisit: '2026-06-15', status: 'due', bhw: 'Gloria Cabrera' },
  { id: 'IMM003', childName: 'Johann Dela Torre', motherName: 'Cynthia Dela Torre', barangay: 'San Jose', dob: '2025-06-10', age: '12 months', vaccines: [{ name: 'BCG', date: '2025-06-10', lot: 'BCG-2023-005', status: 'given' }, { name: 'MMR', date: '2026-06-10', lot: 'MMR-001', status: 'due' }, { name: 'Varicella', date: '2026-06-10', lot: '', status: 'due' }], nextVisit: '2026-06-10', status: 'due', bhw: 'Marilou Domingo' },
  { id: 'IMM004', childName: 'Patricia Lozano', motherName: 'Jennifer Lozano', barangay: 'Poblacion', dob: '2024-11-05', age: '19 months', vaccines: [{ name: 'DPT-HepB-Hib (booster)', date: '2026-05-05', lot: 'Penta-009', status: 'given' }, { name: 'OPV (booster)', date: '2026-05-05', lot: 'OPV-010', status: 'given' }], nextVisit: '2026-11-05', status: 'on_schedule', bhw: 'Natividad Puno' },
  { id: 'IMM005', childName: 'Marc Angelo Buenaventura', motherName: 'Tessie Buenaventura', barangay: 'Halang', dob: '2026-03-01', age: '3 months', vaccines: [{ name: 'BCG', date: '2026-03-01', lot: 'BCG-2025-001', status: 'given' }, { name: 'HepB (birth)', date: '2026-03-01', lot: 'HepB-010', status: 'given' }], nextVisit: '2026-06-01', status: 'overdue', bhw: 'Natividad Puno' },
];

// Maternal Health Records
export const mockMaternalCases = [
  { id: 'MAT001', name: 'Cristina Magpayo', age: 28, barangay: 'San Jose', gravida: 2, para: 1, aog: '28 weeks', lmp: '2025-11-15', edc: '2026-08-22', bloodType: 'A+', prenatalVisits: 5, riskLevel: 'low', risks: [], lastVisit: '2026-06-03', nextVisit: '2026-06-17', midwife: 'Rosario Peralta', status: 'active_prenatal', deliveryPlan: 'RHU', philhealthStatus: 'enrolled', supplements: ['Ferrous Sulfate', 'Folic Acid', 'Calcium'], labResults: { hgb: '11.5 g/dL', hematocrit: '34%', urinalysis: 'Normal', VDRL: 'Non-reactive', HBsAg: 'Non-reactive', HIV: 'Non-reactive' } },
  { id: 'MAT002', name: 'Maribel Quisumbing', age: 22, barangay: 'Halang', gravida: 1, para: 0, aog: '36 weeks', lmp: '2025-09-05', edc: '2026-06-12', bloodType: 'O+', prenatalVisits: 8, riskLevel: 'moderate', risks: ['Gestational Hypertension'], lastVisit: '2026-06-05', nextVisit: '2026-06-10', midwife: 'Rosario Peralta', status: 'active_prenatal', deliveryPlan: 'BMC', philhealthStatus: 'enrolled', supplements: ['Ferrous Sulfate', 'Folic Acid', 'Calcium', 'Methyldopa'], labResults: { hgb: '10.8 g/dL', hematocrit: '32%', urinalysis: 'Protein +1', VDRL: 'Non-reactive', HBsAg: 'Non-reactive', HIV: 'Non-reactive' } },
  { id: 'MAT003', name: 'Florencia Ramos', age: 35, barangay: 'Mabini', gravida: 4, para: 3, aog: '12 weeks', lmp: '2026-03-14', edc: '2026-12-19', bloodType: 'B+', prenatalVisits: 2, riskLevel: 'high', risks: ['Advanced Maternal Age', 'Grand Multipara', 'Prior CS'], lastVisit: '2026-05-28', nextVisit: '2026-06-11', midwife: 'Rosario Peralta', status: 'active_prenatal', deliveryPlan: 'Batangas Provincial Hospital', philhealthStatus: 'enrolled', supplements: ['Ferrous Sulfate', 'Folic Acid', 'Calcium'], labResults: { hgb: '10.2 g/dL', hematocrit: '30%', urinalysis: 'Normal', VDRL: 'Non-reactive', HBsAg: 'Reactive', HIV: 'Non-reactive' } },
  { id: 'MAT004', name: 'Analiza Torralba', age: 26, barangay: 'Kumintang Ilaya', gravida: 1, para: 0, aog: 'Delivered', lmp: '2025-08-20', edc: '2026-05-27', bloodType: 'O-', prenatalVisits: 9, riskLevel: 'low', risks: [], lastVisit: '2026-05-27', nextVisit: '2026-06-10', midwife: 'Rosario Peralta', status: 'postpartum', deliveryPlan: 'RHU', philhealthStatus: 'enrolled', supplements: ['Ferrous Sulfate', 'Vitamin A'], labResults: { hgb: '12.0 g/dL', hematocrit: '36%', urinalysis: 'Normal', VDRL: 'Non-reactive', HBsAg: 'Non-reactive', HIV: 'Non-reactive' } },
  { id: 'MAT005', name: 'Jasmin Villafuerte', age: 17, barangay: 'Poblacion', gravida: 1, para: 0, aog: '20 weeks', lmp: '2026-01-10', edc: '2026-10-17', bloodType: 'A+', prenatalVisits: 3, riskLevel: 'high', risks: ['Adolescent Pregnancy', 'Underweight'], lastVisit: '2026-06-01', nextVisit: '2026-06-15', midwife: 'Rosario Peralta', status: 'active_prenatal', deliveryPlan: 'BMC', philhealthStatus: 'pending', supplements: ['Ferrous Sulfate', 'Folic Acid', 'Calcium', 'Zinc'], labResults: { hgb: '10.0 g/dL', hematocrit: '29%', urinalysis: 'Normal', VDRL: 'Non-reactive', HBsAg: 'Non-reactive', HIV: 'Non-reactive' } },
];

// Family Planning Records
export const mockFPClients = [
  { id: 'FP001', name: 'Sheila Mae Corpus', age: 30, barangay: 'Halang', method: 'Combined Oral Pills', startDate: '2024-03-01', lastSupply: '2026-05-05', nextVisit: '2026-07-05', partner: 'Married', children: 2, acceptorType: 'continuing', sideEffects: 'None', counselor: 'Midwife Rosario Peralta', status: 'active' },
  { id: 'FP002', name: 'Loida Manalo', age: 26, barangay: 'Mabini', method: 'Injectable (DMPA)', startDate: '2025-09-15', lastSupply: '2026-03-15', nextVisit: '2026-06-15', partner: 'Married', children: 1, acceptorType: 'continuing', sideEffects: 'Amenorrhea', counselor: 'Midwife Rosario Peralta', status: 'overdue' },
  { id: 'FP003', name: 'Virgie Gonzales', age: 38, barangay: 'San Jose', method: 'Bilateral Tubal Ligation (BTL)', startDate: '2023-07-10', lastSupply: 'N/A', nextVisit: '2026-07-10', partner: 'Married', children: 4, acceptorType: 'permanent', sideEffects: 'None', counselor: 'Dr. Maria C. Santos', status: 'active' },
  { id: 'FP004', name: 'Rowena Tibayan', age: 22, barangay: 'Poblacion', method: 'Condom', startDate: '2026-01-20', lastSupply: '2026-06-01', nextVisit: '2026-07-01', partner: 'Single', children: 0, acceptorType: 'new', sideEffects: 'None', counselor: 'Midwife Rosario Peralta', status: 'active' },
  { id: 'FP005', name: 'Carmela Ocampo', age: 34, barangay: 'Kumintang Ilaya', method: 'IUD (Copper)', startDate: '2024-11-05', lastSupply: 'N/A', nextVisit: '2027-11-05', partner: 'Married', children: 3, acceptorType: 'continuing', sideEffects: 'Dysmenorrhea', counselor: 'Dr. Maria C. Santos', status: 'active' },
  { id: 'FP006', name: 'Teresita Fajardo', age: 42, barangay: 'Halang', method: 'No Scalpel Vasectomy (NSV) — partner', startDate: '2025-02-14', lastSupply: 'N/A', nextVisit: '2026-08-14', partner: 'Married', children: 5, acceptorType: 'permanent', sideEffects: 'None', counselor: 'Dr. Joseph T. Ramos', status: 'active' },
  { id: 'FP007', name: 'Aileen Camposano', age: 24, barangay: 'Mabini', method: 'LAM (Lactational Amenorrhea)', startDate: '2026-04-10', lastSupply: 'N/A', nextVisit: '2026-10-10', partner: 'Married', children: 1, acceptorType: 'new', sideEffects: 'None', counselor: 'Midwife Rosario Peralta', status: 'active' },
];

// TB-DOTS Cases
export const mockTBCases = [
  { id: 'TB001', name: 'Ernesto Valdez', age: 52, gender: 'Male', barangay: 'Halang', caseType: 'New', classification: 'Pulmonary TB (Bacteriologically Confirmed)', treatmentRegimen: 'Category I (2HRZE / 4HR)', treatmentStartDate: '2026-01-10', phase: 'Continuation', monthsCompleted: 5, totalMonths: 6, nextCollection: '2026-06-15', supporter: 'Natividad Puno (BHW)', weight: '55kg', sputumResults: [{ date: '2026-01-10', result: 'Positive (3+)' }, { date: '2026-03-10', result: 'Negative' }, { date: '2026-05-10', result: 'Negative' }], outcome: 'on_treatment', adherence: 97 },
  { id: 'TB002', name: 'Carmelita Pascua', age: 38, gender: 'Female', barangay: 'Mabini', caseType: 'Relapse', classification: 'Pulmonary TB (Bacteriologically Confirmed)', treatmentRegimen: 'Category II (2HRZES / 1HRZE / 5HRE)', treatmentStartDate: '2026-02-05', phase: 'Intensive', monthsCompleted: 4, totalMonths: 8, nextCollection: '2026-06-05', supporter: 'Gloria Cabrera (BHW)', weight: '48kg', sputumResults: [{ date: '2026-02-05', result: 'Positive (2+)' }, { date: '2026-04-05', result: 'Positive (1+)' }, { date: '2026-06-05', result: 'Negative' }], outcome: 'on_treatment', adherence: 89 },
  { id: 'TB003', name: 'Rodrigo Ilustre', age: 67, gender: 'Male', barangay: 'San Jose', caseType: 'New', classification: 'Pulmonary TB (Clinically Diagnosed)', treatmentRegimen: 'Category I', treatmentStartDate: '2025-12-01', phase: 'Continuation', monthsCompleted: 6, totalMonths: 6, nextCollection: 'N/A', supporter: 'Marilou Domingo (BHW)', weight: '58kg', sputumResults: [{ date: '2025-12-01', result: 'Negative (smear)' }, { date: '2026-02-01', result: 'Negative' }], outcome: 'treatment_completed', adherence: 100 },
  { id: 'TB004', name: 'Marites Aguilar', age: 29, gender: 'Female', barangay: 'Kumintang Ilaya', caseType: 'New', classification: 'Extra-Pulmonary TB (Lymph Node)', treatmentRegimen: 'Category I', treatmentStartDate: '2026-03-20', phase: 'Intensive', monthsCompleted: 3, totalMonths: 6, nextCollection: '2026-06-20', supporter: 'Family member', weight: '50kg', sputumResults: [], outcome: 'on_treatment', adherence: 95 },
  { id: 'TB005', name: 'Danilo Espiritu', age: 44, gender: 'Male', barangay: 'Poblacion', caseType: 'New', classification: 'Pulmonary TB (Bacteriologically Confirmed)', treatmentRegimen: 'Category I', treatmentStartDate: '2026-04-15', phase: 'Intensive', monthsCompleted: 2, totalMonths: 6, nextCollection: '2026-06-15', supporter: 'Natividad Puno (BHW)', weight: '62kg', sputumResults: [{ date: '2026-04-15', result: 'Positive (1+)' }], outcome: 'on_treatment', adherence: 85 },
];

// Nutrition Program
export const mockNutritionCases = [
  { id: 'NUT001', name: 'Angelo Lim', age: 2, ageMonths: 24, gender: 'Male', barangay: 'Halang', motherName: 'Ligaya Lim', weight: 9.8, height: 80.5, muac: 11.5, classification: 'SAM', bmi: 15.1, nutritionStatus: 'Severely Underweight', interventions: ['Ready-to-use Therapeutic Food (RUTF)', 'Vitamin A', 'Iron', 'Monthly monitoring'], lastVisit: '2026-06-01', nextVisit: '2026-06-15', bhw: 'Natividad Puno', program: 'Operation Timbang Plus', philhealthCoverage: false },
  { id: 'NUT002', name: 'Christine Joy Padilla', age: 4, ageMonths: 48, gender: 'Female', barangay: 'Mabini', motherName: 'Rosario Padilla', weight: 13.5, height: 97.0, muac: 13.0, classification: 'MAM', bmi: 14.3, nutritionStatus: 'Moderately Underweight', interventions: ['Supplementary feeding', 'Vitamin A', 'Deworming', 'Nutrition counseling'], lastVisit: '2026-05-28', nextVisit: '2026-06-28', bhw: 'Gloria Cabrera', program: 'Operation Timbang Plus', philhealthCoverage: false },
  { id: 'NUT003', name: 'Rovic Bautista', age: 3, ageMonths: 36, gender: 'Male', barangay: 'San Jose', motherName: 'Edna Bautista', weight: 13.8, height: 93.0, muac: 13.8, classification: 'Normal', bmi: 15.9, nutritionStatus: 'Normal', interventions: ['Vitamin A', 'Iron'], lastVisit: '2026-06-05', nextVisit: '2026-12-05', bhw: 'Marilou Domingo', program: 'Operation Timbang Plus', philhealthCoverage: false },
  { id: 'NUT004', name: 'Angelica Ferrer', age: 5, ageMonths: 60, gender: 'Female', barangay: 'Kumintang Ilaya', motherName: 'Perla Ferrer', weight: 14.0, height: 105.0, muac: 12.5, classification: 'MAM', bmi: 12.7, nutritionStatus: 'Moderately Underweight', interventions: ['Supplementary feeding', 'Micronutrient powder', 'Deworming'], lastVisit: '2026-05-20', nextVisit: '2026-06-20', bhw: 'Conception Aguila', program: 'Operation Timbang Plus', philhealthCoverage: false },
  { id: 'NUT005', name: 'Jayson Mendoza', age: 1, ageMonths: 18, gender: 'Male', barangay: 'Poblacion', motherName: 'Michelle Mendoza', weight: 8.0, height: 74.0, muac: 12.0, classification: 'SAM', bmi: 14.6, nutritionStatus: 'Severely Underweight', interventions: ['RUTF', 'Vitamin A', 'Amoxicillin', 'Weekly monitoring'], lastVisit: '2026-06-08', nextVisit: '2026-06-15', bhw: 'Elena Valerio', program: 'Operation Timbang Plus', philhealthCoverage: false },
];

// Disease Surveillance (PIDSR)
export const mockDiseaseReports = [
  { id: 'DSR001', disease: 'Dengue Fever', icd10: 'A97.0', reportingWeek: '2026-W23', cases: 4, deaths: 0, barangays: ['Halang', 'Mabini'], ageGroups: { '0-4': 1, '5-14': 2, '15-49': 1, '50+': 0 }, actionTaken: 'Fogging conducted, case investigation, vector control', reportedBy: 'Dr. Maria C. Santos', status: 'verified', alert: true },
  { id: 'DSR002', disease: 'Leptospirosis', icd10: 'A27.9', reportingWeek: '2026-W23', cases: 1, deaths: 0, barangays: ['San Jose'], ageGroups: { '0-4': 0, '5-14': 0, '15-49': 1, '50+': 0 }, actionTaken: 'Case management, exposure investigation, prophylaxis given to contacts', reportedBy: 'Dr. Maria C. Santos', status: 'verified', alert: false },
  { id: 'DSR003', disease: 'Acute Bloody Diarrhea', icd10: 'A09', reportingWeek: '2026-W22', cases: 2, deaths: 0, barangays: ['Poblacion'], ageGroups: { '0-4': 2, '5-14': 0, '15-49': 0, '50+': 0 }, actionTaken: 'Stool culture, ORS given, water sampling, health education', reportedBy: 'Dr. Joseph T. Ramos', status: 'verified', alert: false },
  { id: 'DSR004', disease: 'Pneumonia', icd10: 'J18', reportingWeek: '2026-W23', cases: 6, deaths: 0, barangays: ['Halang', 'Kumintang Ilaya', 'San Jose'], ageGroups: { '0-4': 4, '5-14': 1, '15-49': 0, '50+': 1 }, actionTaken: 'Case management, antibiotics, referrals as indicated', reportedBy: 'Dr. Maria C. Santos', status: 'verified', alert: false },
  { id: 'DSR005', disease: 'Animal Bite (Dog, Cat)', icd10: 'W54', reportingWeek: '2026-W23', cases: 3, deaths: 0, barangays: ['Mabini', 'Halang'], ageGroups: { '0-4': 0, '5-14': 2, '15-49': 1, '50+': 0 }, actionTaken: 'Wound washing, Anti-Rabies Vaccine initiated, ICTV coordination', reportedBy: 'Dr. Joseph T. Ramos', status: 'verified', alert: false },
  { id: 'DSR006', disease: 'Tuberculosis (New Cases)', icd10: 'A15', reportingWeek: '2026-W22', cases: 2, deaths: 0, barangays: ['Halang', 'Poblacion'], ageGroups: { '0-4': 0, '5-14': 0, '15-49': 1, '50+': 1 }, actionTaken: 'GeneXpert testing, DOTS initiated, contact tracing', reportedBy: 'Dr. Maria C. Santos', status: 'verified', alert: false },
];

// Vital Statistics
export const mockVitalRecords = [
  { id: 'VS001', type: 'Birth', name: 'Baby Boy Reyes', date: '2026-06-01', barangay: 'Halang', motherName: 'Ana Reyes', fatherName: 'Pedro Reyes', attendant: 'Midwife Rosario Peralta', birthPlace: 'RHU', weight: '3.2 kg', apgar: '9/10', registrationStatus: 'registered', lncrn: '2026-60001', remarks: 'Normal spontaneous delivery' },
  { id: 'VS002', type: 'Birth', name: 'Baby Girl Santos', date: '2026-05-28', barangay: 'Mabini', motherName: 'Gloria Santos', fatherName: 'Eduardo Santos', attendant: 'Dr. Maria C. Santos', birthPlace: 'RHU', weight: '2.9 kg', apgar: '8/9', registrationStatus: 'registered', lncrn: '2026-60002', remarks: 'Normal spontaneous delivery, neonatal jaundice' },
  { id: 'VS003', type: 'Death', name: 'Isidro Navarro', date: '2026-06-05', barangay: 'San Jose', age: 78, gender: 'Male', causeOfDeath: 'Myocardial Infarction', secondaryCause: 'Hypertension, Type 2 DM', attendant: 'Dr. Joseph T. Ramos', placeOfDeath: 'Home', registrationStatus: 'registered', deathrn: '2026-D-001', remarks: 'Natural death' },
  { id: 'VS004', type: 'Birth', name: 'Baby Boy Dela Cruz', date: '2026-06-08', barangay: 'Kumintang Ilaya', motherName: 'Analiza Dela Cruz', fatherName: 'Fernando Dela Cruz', attendant: 'Midwife Rosario Peralta', birthPlace: 'BMC (referred)', weight: '2.5 kg', apgar: '7/8', registrationStatus: 'pending', lncrn: '', remarks: 'Low birth weight, referred to BMC for monitoring' },
  { id: 'VS005', type: 'Fetal Death', name: 'Unnamed', date: '2026-05-15', barangay: 'Poblacion', motherName: 'Imelda Chua', fatherName: 'Wilson Chua', attendant: 'Midwife Rosario Peralta', birthPlace: 'RHU', weight: '1.8 kg', apgar: 'N/A', registrationStatus: 'registered', lncrn: '2026-FD-001', remarks: '28 weeks AOG, stillbirth' },
  { id: 'VS006', type: 'Death', name: 'Leticia Buencamino', date: '2026-05-30', barangay: 'Halang', age: 65, gender: 'Female', causeOfDeath: 'Cerebrovascular Accident', secondaryCause: 'Hypertension', attendant: 'Dr. Maria C. Santos', placeOfDeath: 'RHU', registrationStatus: 'registered', deathrn: '2026-D-002', remarks: 'Died on arrival at RHU' },
];

// Medicine Inventory
export const mockMedicineInventory = [
  { id: 'MED001', genericName: 'Amoxicillin', brandName: 'Generic', form: 'Capsule 500mg', stock: 850, unit: 'capsules', reorderLevel: 200, category: 'Antibiotic', expiryDate: '2027-03-31', batchNo: 'AMX-2025-001', source: 'DOH-CHD', unitCost: 4.50, status: 'adequate', usage30days: 320 },
  { id: 'MED002', genericName: 'Paracetamol', brandName: 'Generic', form: 'Tablet 500mg', stock: 2200, unit: 'tablets', reorderLevel: 500, category: 'Analgesic/Antipyretic', expiryDate: '2027-06-30', batchNo: 'PAR-2025-003', source: 'DOH-CHD', unitCost: 1.20, status: 'good', usage30days: 480 },
  { id: 'MED003', genericName: 'Ferrous Sulfate', brandName: 'Generic', form: 'Tablet 325mg', stock: 120, unit: 'tablets', reorderLevel: 300, category: 'Hematinics', expiryDate: '2026-09-30', batchNo: 'FES-2024-002', source: 'DOH-CHD', unitCost: 2.00, status: 'low', usage30days: 350 },
  { id: 'MED004', genericName: 'Amlodipine', brandName: 'Generic', form: 'Tablet 5mg', stock: 450, unit: 'tablets', reorderLevel: 150, category: 'Antihypertensive', expiryDate: '2027-01-31', batchNo: 'AML-2025-001', source: 'DOH-CHD', unitCost: 3.80, status: 'adequate', usage30days: 180 },
  { id: 'MED005', genericName: 'Metformin HCl', brandName: 'Generic', form: 'Tablet 500mg', stock: 60, unit: 'tablets', reorderLevel: 200, category: 'Antidiabetic', expiryDate: '2026-12-31', batchNo: 'MET-2025-002', source: 'DOH-CHD', unitCost: 3.00, status: 'critical', usage30days: 240 },
  { id: 'MED006', genericName: 'Co-trimoxazole', brandName: 'Generic', form: 'Tablet 400/80mg', stock: 380, unit: 'tablets', reorderLevel: 100, category: 'Antibiotic', expiryDate: '2027-04-30', batchNo: 'CTX-2025-001', source: 'DOH-CHD', unitCost: 2.50, status: 'adequate', usage30days: 120 },
  { id: 'MED007', genericName: 'ORS Sachet', brandName: 'Generic', form: 'Powder sachet', stock: 200, unit: 'sachets', reorderLevel: 100, category: 'Oral Rehydration', expiryDate: '2027-08-31', batchNo: 'ORS-2025-003', source: 'DOH-CHD', unitCost: 5.00, status: 'adequate', usage30days: 45 },
  { id: 'MED008', genericName: 'Isoniazid', brandName: 'Generic', form: 'Tablet 300mg', stock: 180, unit: 'tablets', reorderLevel: 50, category: 'Anti-TB', expiryDate: '2027-02-28', batchNo: 'INH-2025-001', source: 'NTP-CHD', unitCost: 2.20, status: 'good', usage30days: 30 },
  { id: 'MED009', genericName: 'Rifampicin', brandName: 'Generic', form: 'Capsule 600mg', stock: 180, unit: 'capsules', reorderLevel: 50, category: 'Anti-TB', expiryDate: '2027-02-28', batchNo: 'RIF-2025-001', source: 'NTP-CHD', unitCost: 5.50, status: 'good', usage30days: 30 },
  { id: 'MED010', genericName: 'Vitamin A', brandName: 'Generic', form: 'Capsule 200,000 IU', stock: 500, unit: 'capsules', reorderLevel: 150, category: 'Vitamins', expiryDate: '2026-11-30', batchNo: 'VTA-2025-001', source: 'DOH-CHD', unitCost: 8.00, status: 'adequate', usage30days: 95 },
  { id: 'MED011', genericName: 'Folic Acid', brandName: 'Generic', form: 'Tablet 5mg', stock: 80, unit: 'tablets', reorderLevel: 200, category: 'Vitamins', expiryDate: '2026-09-30', batchNo: 'FOL-2024-005', source: 'DOH-CHD', unitCost: 1.50, status: 'critical', usage30days: 280 },
  { id: 'MED012', genericName: 'Anti-Rabies Vaccine', brandName: 'Verorab', form: 'Vial 1mL', stock: 15, unit: 'vials', reorderLevel: 10, category: 'Vaccine', expiryDate: '2026-12-31', batchNo: 'ARV-2025-002', source: 'DOH-CHD', unitCost: 450.00, status: 'low', usage30days: 6 },
];

// Health Certificates Issued
export const mockHealthCertificates = [
  { id: 'HC001', type: 'Medical Certificate', recipientName: 'Angelo Reyes', age: 25, barangay: 'Halang', purpose: 'Employment', issuedBy: 'Dr. Maria C. Santos', issuedDate: '2026-06-10', validUntil: '2026-07-10', certNo: 'HC-2026-001', fee: 50, status: 'issued', findings: 'Fit to work, no communicable disease' },
  { id: 'HC002', type: 'Health Certificate', recipientName: 'Mercedita Cruz', age: 38, barangay: 'Mabini', purpose: 'Food Handler', issuedBy: 'Dr. Maria C. Santos', issuedDate: '2026-06-09', validUntil: '2027-06-09', certNo: 'HC-2026-002', fee: 100, status: 'issued', findings: 'Fit for food handling, stool exam negative' },
  { id: 'HC003', type: 'Certificate of Death', recipientName: 'Isidro Navarro (deceased)', age: 78, barangay: 'San Jose', purpose: 'Civil Registration', issuedBy: 'Dr. Joseph T. Ramos', issuedDate: '2026-06-05', validUntil: 'N/A', certNo: 'CD-2026-001', fee: 0, status: 'issued', findings: 'Myocardial infarction as cause of death' },
  { id: 'HC004', type: 'Medical Certificate', recipientName: 'Joanna Sison', age: 20, barangay: 'San Jose', purpose: 'School Enrollment', issuedBy: 'Dr. Maria C. Santos', issuedDate: '2026-06-08', validUntil: '2026-12-08', certNo: 'HC-2026-003', fee: 50, status: 'issued', findings: 'Physically fit for school activities' },
  { id: 'HC005', type: 'Certificate of Live Birth', recipientName: 'Baby Boy Reyes', age: 0, barangay: 'Halang', purpose: 'Civil Registration', issuedBy: 'Midwife Rosario Peralta', issuedDate: '2026-06-01', validUntil: 'N/A', certNo: 'LB-2026-001', fee: 0, status: 'issued', findings: 'Normal spontaneous delivery, 3.2 kg' },
  { id: 'HC006', type: 'Medical Certificate', recipientName: 'Rodel Buenaventura', age: 45, barangay: 'Kumintang Ilaya', purpose: 'Driver\'s License', issuedBy: 'Dr. Joseph T. Ramos', issuedDate: '2026-06-07', validUntil: '2027-06-07', certNo: 'HC-2026-004', fee: 50, status: 'issued', findings: 'Physically and mentally fit to drive' },
  { id: 'HC007', type: 'Barangay Health Certificate', recipientName: 'Lucinda Flores', age: 55, barangay: 'Poblacion', purpose: 'Business Permit', issuedBy: 'Dr. Maria C. Santos', issuedDate: '2026-06-06', validUntil: '2026-12-31', certNo: 'BHC-2026-001', fee: 100, status: 'issued', findings: 'Establishment meets health standards' },
];

// Sanitation & Environmental Health Inspections
export const mockSanitationInspections = [
  { id: 'SI001', establishment: 'Mabini Carinderia', type: 'Food Establishment', barangay: 'Mabini', inspector: 'Sanitary Inspector Ramon Villareal', inspectionDate: '2026-06-08', findings: ['Food handlers without health certificate', 'No proper waste disposal'], violations: 2, status: 'failed', nextInspection: '2026-06-22', complianceRate: 65 },
  { id: 'SI002', establishment: 'Halang Elementary School Canteen', type: 'School Canteen', barangay: 'Halang', inspector: 'Sanitary Inspector Ramon Villareal', inspectionDate: '2026-06-05', findings: ['Minor: Handwashing soap supply insufficient'], violations: 1, status: 'conditional', nextInspection: '2026-07-05', complianceRate: 88 },
  { id: 'SI003', establishment: 'Poblacion Public Market', type: 'Public Market', barangay: 'Poblacion', inspector: 'Sanitary Inspector Ramon Villareal', inspectionDate: '2026-06-03', findings: ['Proper waste segregation', 'Clean surroundings', 'All vendors with health certificates'], violations: 0, status: 'passed', nextInspection: '2026-09-03', complianceRate: 98 },
  { id: 'SI004', establishment: 'San Jose Barbershop', type: 'Personal Care Service', barangay: 'San Jose', inspector: 'Sanitary Inspector Ramon Villareal', inspectionDate: '2026-06-07', findings: ['Sterilization procedures not followed'], violations: 1, status: 'conditional', nextInspection: '2026-06-21', complianceRate: 72 },
  { id: 'SI005', establishment: 'Kumintang Water Refilling Station', type: 'Water Refilling', barangay: 'Kumintang Ilaya', inspector: 'Sanitary Inspector Ramon Villareal', inspectionDate: '2026-06-04', findings: ['Valid sanitary permit', 'Water analysis up to date', 'Clean equipment'], violations: 0, status: 'passed', nextInspection: '2026-09-04', complianceRate: 100 },
];

// ============================================================
// RHU CORE DATA
// ============================================================

export const RHU_INFO = {
  name: 'Nasugbu Rural Health Unit I',
  code: 'RHU-NSG-001',
  municipality: 'Nasugbu',
  province: 'Batangas',
  region: 'Region IV-A (CALABARZON)',
  address: 'Poblacion, Nasugbu, Batangas',
  contactNumber: '(043) 416-1234',
  email: 'rhu1.nasugbu@doh.gov.ph',
  catchmentBarangays: ['Halang', 'Mabini', 'San Jose', 'Poblacion', 'Kumintang Ilaya', 'Kumintang Ibaba', 'Alangilan', 'Bolbok'],
  totalPopulation: 48230,
  staffCount: 12,
  bhwCount: 47,
  operatingHours: 'Mon–Fri: 8:00 AM – 5:00 PM',
  chiefMHO: 'Dr. Rosalinda V. Castillo',
};

export const mockRHUInventory = [
  { id: 'INV001', bloodType: 'A+' as BloodType, units: 15, status: 'adequate', lastUpdated: '2026-06-10', expiryDate: '2026-06-24', source: 'Philippine Red Cross' },
  { id: 'INV002', bloodType: 'A-' as BloodType, units: 3, status: 'low', lastUpdated: '2026-06-10', expiryDate: '2026-06-20', source: 'Philippine Red Cross' },
  { id: 'INV003', bloodType: 'B+' as BloodType, units: 12, status: 'adequate', lastUpdated: '2026-06-09', expiryDate: '2026-06-23', source: 'Philippine Red Cross' },
  { id: 'INV004', bloodType: 'B-' as BloodType, units: 2, status: 'critical', lastUpdated: '2026-06-09', expiryDate: '2026-06-21', source: 'Philippine Red Cross' },
  { id: 'INV005', bloodType: 'O+' as BloodType, units: 20, status: 'good', lastUpdated: '2026-06-10', expiryDate: '2026-06-25', source: 'DOH Blood Drive' },
  { id: 'INV006', bloodType: 'O-' as BloodType, units: 4, status: 'low', lastUpdated: '2026-06-08', expiryDate: '2026-06-19', source: 'Philippine Red Cross' },
  { id: 'INV007', bloodType: 'AB+' as BloodType, units: 8, status: 'adequate', lastUpdated: '2026-06-07', expiryDate: '2026-06-22', source: 'Philippine Red Cross' },
  { id: 'INV008', bloodType: 'AB-' as BloodType, units: 1, status: 'critical', lastUpdated: '2026-06-07', expiryDate: '2026-06-18', source: 'Philippine Red Cross' },
];

export const mockBloodDrives = [
  { id: 'BD001', title: 'Nasugbu Municipal Hall Blood Drive', date: '2026-06-20', venue: 'City Hall Lobby', organizer: 'RHU + Philippine Red Cross', targetUnits: 50, registeredDonors: 38, unitsCollected: 0, status: 'scheduled', barangays: ['Poblacion', 'Kumintang Ilaya'], contactPerson: 'Maria Santos', notes: 'Pre-registration required' },
  { id: 'BD002', title: 'Halang Barangay Health Drive', date: '2026-06-07', venue: 'Halang Barangay Hall', organizer: 'RHU + BHW Team', targetUnits: 30, registeredDonors: 28, unitsCollected: 25, status: 'completed', barangays: ['Halang'], contactPerson: 'Natividad Puno', notes: '3 donors deferred (low hemoglobin)' },
  { id: 'BD003', title: 'Batangas State University Drive', date: '2026-07-15', venue: 'BatStateU Main Campus', organizer: 'RHU + BatStateU Nursing', targetUnits: 80, registeredDonors: 0, unitsCollected: 0, status: 'scheduled', barangays: ['Alangilan'], contactPerson: 'Dr. Joseph Ramos', notes: 'Coordination ongoing' },
  { id: 'BD004', title: 'San Jose Community Drive', date: '2026-05-25', venue: 'San Jose Multi-purpose Hall', organizer: 'RHU', targetUnits: 25, registeredDonors: 22, unitsCollected: 19, status: 'completed', barangays: ['San Jose'], contactPerson: 'Marilou Domingo', notes: 'All units sent to Provincial Hospital' },
];

export const mockPatients = [
  { id: 'P001', name: 'Ricardo Dimayuga', age: 65, gender: 'Male', bloodType: 'B+', barangay: 'Mabini', admissionDate: '2026-06-10', diagnosis: 'Acute Coronary Syndrome (r/o)', physician: 'Dr. Maria C. Santos', ward: 'Observation', status: 'admitted', philhealthNo: 'PH-123456789', contactNo: '09171112233' },
  { id: 'P002', name: 'Maribel Quisumbing', age: 22, gender: 'Female', bloodType: 'O+', barangay: 'Halang', admissionDate: '2026-06-09', diagnosis: 'Gestational Hypertension, 36 weeks AOG', physician: 'Dr. Maria C. Santos', ward: 'Lying-in', status: 'admitted', philhealthNo: 'PH-234567890', contactNo: '09181234567' },
  { id: 'P003', name: 'Alfonso Nakpil', age: 8, gender: 'Male', bloodType: 'A+', barangay: 'Poblacion', admissionDate: '2026-06-09', diagnosis: 'Community Acquired Pneumonia', physician: 'Dr. Joseph T. Ramos', ward: 'Pediatric', status: 'admitted', philhealthNo: 'PH-345678901', contactNo: '09191234567' },
  { id: 'P004', name: 'Felicitas Aguilar', age: 71, gender: 'Female', bloodType: 'AB+', barangay: 'Kumintang Ilaya', admissionDate: '2026-06-08', diagnosis: 'Hip fracture, right', physician: 'Dr. Joseph T. Ramos', ward: 'Surgical', status: 'discharged', philhealthNo: 'PH-456789012', contactNo: '09201234567' },
  { id: 'P005', name: 'Danilo Espiritu', age: 44, gender: 'Male', bloodType: 'O-', barangay: 'Poblacion', admissionDate: '2026-06-07', diagnosis: 'Pulmonary TB — Initiation of DOTS', physician: 'Dr. Maria C. Santos', ward: 'Isolation', status: 'admitted', philhealthNo: 'PH-567890123', contactNo: '09211234567' },
  { id: 'P006', name: 'Jasmin Villafuerte', age: 17, gender: 'Female', bloodType: 'A+', barangay: 'Poblacion', admissionDate: '2026-06-01', diagnosis: 'Threatened abortion, 20 weeks AOG', physician: 'Dr. Maria C. Santos', ward: 'Lying-in', status: 'discharged', philhealthNo: '', contactNo: '09221234567' },
];

export const mockTransfusions = [
  { id: 'TR001', patientName: 'Ricardo Dimayuga', patientId: 'P001', bloodType: 'B+', units: 2, component: 'Packed RBC', date: '2026-06-10', transfusedBy: 'Dr. Maria C. Santos', nurse: 'RN Clara Mendez', source: 'INV003', preTransfusionHgb: '7.2 g/dL', postTransfusionHgb: '9.8 g/dL', reaction: 'None', status: 'completed' },
  { id: 'TR002', patientName: 'Maribel Quisumbing', patientId: 'P002', bloodType: 'O+', units: 1, component: 'Fresh Frozen Plasma', date: '2026-06-09', transfusedBy: 'Dr. Maria C. Santos', nurse: 'RN Clara Mendez', source: 'INV005', preTransfusionHgb: '8.5 g/dL', postTransfusionHgb: '10.1 g/dL', reaction: 'None', status: 'completed' },
  { id: 'TR003', patientName: 'Felicitas Aguilar', patientId: 'P004', bloodType: 'AB+', units: 2, component: 'Packed RBC', date: '2026-06-08', transfusedBy: 'Dr. Joseph T. Ramos', nurse: 'RN Jose Figueroa', source: 'INV007', preTransfusionHgb: '6.8 g/dL', postTransfusionHgb: '9.2 g/dL', reaction: 'None', status: 'completed' },
];

export const mockReferrals = [
  { id: 'REF001', patientName: 'Ricardo Dimayuga', age: 65, gender: 'Male', referralDate: '2026-06-10', referringMD: 'Dr. Maria C. Santos', referredTo: 'Batangas Provincial Hospital - Cardiology', diagnosis: 'Acute Coronary Syndrome (r/o)', reason: 'For 12-lead ECG, troponin assay, and specialist consult', urgency: 'urgent', status: 'pending', contactReceiving: '(043) 416-7777', feedback: '', philhealthCharged: true },
  { id: 'REF002', patientName: 'Florencia Ramos', age: 35, gender: 'Female', referralDate: '2026-06-09', referringMD: 'Midwife Rosario Peralta', referredTo: 'Batangas Provincial Hospital - OB-GYN', diagnosis: 'High-risk pregnancy — HBsAg Reactive, prior CS', reason: 'For facility delivery and specialist management', urgency: 'moderate', status: 'accepted', contactReceiving: '(043) 723-0555', feedback: 'Scheduled for admission on June 11', philhealthCharged: true },
  { id: 'REF003', patientName: 'Baby Boy Dela Cruz', age: 0, gender: 'Male', referralDate: '2026-06-08', referringMD: 'Midwife Rosario Peralta', referredTo: 'Batangas Provincial Hospital - NICU', diagnosis: 'Low birth weight (2.5 kg)', reason: 'For neonatal monitoring and feeding support', urgency: 'urgent', status: 'completed', contactReceiving: '(043) 416-7777', feedback: 'Stable, discharged June 10 with weight 2.6 kg', philhealthCharged: true },
  { id: 'REF004', patientName: 'Angelo Lim', age: 2, gender: 'Male', referralDate: '2026-06-05', referringMD: 'Dr. Maria C. Santos', referredTo: 'DOH Nutrition Rehabilitation Center', diagnosis: 'Severe Acute Malnutrition (SAM)', reason: 'For intensive therapeutic feeding and monitoring', urgency: 'moderate', status: 'pending', contactReceiving: '(043) 723-1111', feedback: '', philhealthCharged: false },
  { id: 'REF005', patientName: 'Carmelita Pascua', age: 38, gender: 'Female', referralDate: '2026-06-01', referringMD: 'Dr. Maria C. Santos', referredTo: 'PMDT Center — Batangas Regional Hospital', diagnosis: 'TB Relapse — possible drug resistance', reason: 'For GeneXpert MTB/RIF assay, DST, and PMDT evaluation', urgency: 'moderate', status: 'accepted', contactReceiving: '(043) 980-0001', feedback: 'GeneXpert scheduled June 12', philhealthCharged: true },
];

export const mockBHWs = [
  { id: 'BHW001', name: 'Natividad Puno', barangay: 'Halang', contactNo: '09171110001', yearsOfService: 8, trainingLevel: 'Senior BHW', activeStatus: true, householdsAssigned: 62, donorsReferred: 28, immunizationCoverage: 94, maternalCases: 12, lastTraining: '2025-11-15', supervisor: 'Midwife Rosario Peralta', responsibilities: ['Home visits', 'Nutrition monitoring', 'Immunization reminders', 'TB-DOTS support'] },
  { id: 'BHW002', name: 'Gloria Cabrera', barangay: 'Mabini', contactNo: '09171110002', yearsOfService: 5, trainingLevel: 'Regular BHW', activeStatus: true, householdsAssigned: 55, donorsReferred: 19, immunizationCoverage: 91, maternalCases: 9, lastTraining: '2025-11-15', supervisor: 'Midwife Rosario Peralta', responsibilities: ['Home visits', 'Family planning counseling', 'PIDSR reporting'] },
  { id: 'BHW003', name: 'Marilou Domingo', barangay: 'San Jose', contactNo: '09171110003', yearsOfService: 12, trainingLevel: 'Senior BHW', activeStatus: true, householdsAssigned: 70, donorsReferred: 35, immunizationCoverage: 97, maternalCases: 15, lastTraining: '2026-01-20', supervisor: 'Midwife Rosario Peralta', responsibilities: ['Home visits', 'TB-DOTS support', 'Blood drive coordination', 'Nutrition monitoring'] },
  { id: 'BHW004', name: 'Conception Aguila', barangay: 'Kumintang Ilaya', contactNo: '09171110004', yearsOfService: 3, trainingLevel: 'Regular BHW', activeStatus: true, householdsAssigned: 48, donorsReferred: 12, immunizationCoverage: 88, maternalCases: 7, lastTraining: '2025-11-15', supervisor: 'Midwife Rosario Peralta', responsibilities: ['Home visits', 'Nutrition monitoring', 'Immunization reminders'] },
  { id: 'BHW005', name: 'Elena Valerio', barangay: 'Poblacion', contactNo: '09171110005', yearsOfService: 6, trainingLevel: 'Regular BHW', activeStatus: true, householdsAssigned: 58, donorsReferred: 22, immunizationCoverage: 90, maternalCases: 11, lastTraining: '2025-11-15', supervisor: 'Midwife Rosario Peralta', responsibilities: ['Home visits', 'Family planning follow-up', 'Sanitation inspections assistance'] },
];

export const mockDOHReports = [
  { id: 'DOH001', reportType: 'FHSIS Monthly Report', period: 'May 2026', submittedBy: 'Dr. Maria C. Santos', submittedDate: '2026-06-05', status: 'submitted', acceptedBy: 'CHD IV-A', modules: ['OPD Consultations', 'Maternal Health', 'Family Planning', 'Child Health', 'Disease Surveillance'], remarks: 'On time submission' },
  { id: 'DOH002', reportType: 'PIDSR Weekly Report', period: 'Week 23 (Jun 2–8, 2026)', submittedBy: 'Dr. Joseph T. Ramos', submittedDate: '2026-06-09', status: 'submitted', acceptedBy: 'RESU IV-A', modules: ['Dengue', 'Leptospirosis', 'Pneumonia', 'Animal Bite'], remarks: 'Dengue alert noted — cluster in Halang' },
  { id: 'DOH003', reportType: 'NTP Quarterly Report', period: 'Q1 2026 (Jan–Mar)', submittedBy: 'Dr. Maria C. Santos', submittedDate: '2026-04-10', status: 'submitted', acceptedBy: 'Provincial NTP Coordinator', modules: ['TB Case Finding', 'DOTS Outcomes', 'Drug Inventory'], remarks: 'Treatment success rate: 89%' },
  { id: 'DOH004', reportType: 'EPI Monthly Report', period: 'May 2026', submittedBy: 'Midwife Rosario Peralta', submittedDate: '2026-06-03', status: 'submitted', acceptedBy: 'CHD IV-A', modules: ['Immunization Coverage', 'Cold Chain', 'Vaccine Wastage'], remarks: 'Coverage target met for BCG, HepB, Pentavalent' },
  { id: 'DOH005', reportType: 'FHSIS Monthly Report', period: 'June 2026', submittedBy: 'Dr. Maria C. Santos', submittedDate: '', status: 'pending', acceptedBy: '', modules: ['OPD Consultations', 'Maternal Health', 'Family Planning', 'Child Health', 'Disease Surveillance'], remarks: 'Due: July 5, 2026' },
  { id: 'DOH006', reportType: 'Nutrition Month Report', period: 'July 2026', submittedBy: '', submittedDate: '', status: 'upcoming', acceptedBy: '', modules: ['OPT+ Results', 'SAM/MAM Cases', 'Supplementary Feeding'], remarks: 'National Nutrition Month — due July 31' },
];

export const mockRHUStaff = [
  { id: 'ST001', name: 'Dr. Maria C. Santos', position: 'Municipal Health Officer', specialization: 'Public Health', employmentType: 'Plantilla', licenseNo: 'MD-2005-12345', prcExpiry: '2026-06-30', philhealthAccreditation: 'Active', schedule: 'Mon–Fri: 8AM–5PM', contactNo: '09178001001', email: 'mcsantos.mho@nasugbu.gov.ph', status: 'active' },
  { id: 'ST002', name: 'Dr. Joseph T. Ramos', position: 'Rural Health Physician', specialization: 'General Practice', employmentType: 'Plantilla', licenseNo: 'MD-2010-67890', prcExpiry: '2026-06-30', philhealthAccreditation: 'Active', schedule: 'Mon–Fri: 8AM–5PM', contactNo: '09178002002', email: 'jtramos@nasugbu.gov.ph', status: 'active' },
  { id: 'ST003', name: 'Midwife Rosario Peralta', position: 'Rural Midwife', specialization: 'Midwifery/OB', employmentType: 'Plantilla', licenseNo: 'RM-2008-11111', prcExpiry: '2027-04-30', philhealthAccreditation: 'Active', schedule: 'Mon–Fri: 8AM–5PM', contactNo: '09178003003', email: 'rperalta@nasugbu.gov.ph', status: 'active' },
  { id: 'ST004', name: 'RN Clara Mendez', position: 'Public Health Nurse I', specialization: 'Public Health Nursing', employmentType: 'Plantilla', licenseNo: 'RN-2015-22222', prcExpiry: '2027-07-31', philhealthAccreditation: 'Active', schedule: 'Mon–Fri: 8AM–5PM', contactNo: '09178004004', email: 'cmendez@nasugbu.gov.ph', status: 'active' },
  { id: 'ST005', name: 'RN Jose Figueroa', position: 'Public Health Nurse II', specialization: 'Epidemiology', employmentType: 'Contractual', licenseNo: 'RN-2018-33333', prcExpiry: '2026-09-30', philhealthAccreditation: 'Pending', schedule: 'Mon–Fri: 8AM–5PM', contactNo: '09178005005', email: 'jfigueroa@nasugbu.gov.ph', status: 'active' },
  { id: 'ST006', name: 'Ramon Villareal', position: 'Sanitary Inspector', specialization: 'Environmental Health', employmentType: 'Plantilla', licenseNo: 'SI-2012-44444', prcExpiry: '2027-01-31', philhealthAccreditation: 'N/A', schedule: 'Mon–Fri: 8AM–5PM', contactNo: '09178006006', email: 'rvillareal@nasugbu.gov.ph', status: 'active' },
];