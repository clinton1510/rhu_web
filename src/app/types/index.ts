export type BloodType = 'A+' | 'A-' | 'B+' | 'B-' | 'AB+' | 'AB-' | 'O+' | 'O-';

export type UrgencyLevel = 'critical' | 'urgent' | 'moderate' | 'scheduled';

export interface Donor {
  id: string;
  name: string;
  bloodType: BloodType;
  location: {
    lat: number;
    lng: number;
    address: string;
  };
  availability: boolean;
  donationHistory: number;
  lastDonation?: Date;
  responseRate: number; // 0-100
  avgResponseTime: number; // minutes
  cluster: 'reliable' | 'moderate' | 'new';
  contactNumber: string;
  email: string;
  verified: boolean;
}

export interface BloodRequest {
  id: string;
  hospitalId: string;
  hospitalName: string;
  bloodType: BloodType;
  quantity: number; // in units
  urgency: UrgencyLevel;
  location: {
    lat: number;
    lng: number;
    address: string;
  };
  requestedAt: Date;
  neededBy: Date;
  status: 'pending' | 'matching' | 'fulfilled' | 'expired';
  patientInfo?: string;
}

export interface DonorMatch {
  donor: Donor;
  distance: number; // km
  eta: number; // minutes
  route?: Array<[number, number]>;
  responseProbability: number; // 0-100 (ML prediction)
  score: number; // combined score
}

export interface DemandForecast {
  date: string;
  predicted: number;
  actual?: number;
  bloodType: BloodType;
  confidence: number;
}

export interface DonorBehaviorCluster {
  cluster: 'reliable' | 'moderate' | 'new';
  count: number;
  avgResponseTime: number;
  avgResponseRate: number;
  characteristics: string[];
}

export interface BloodBank {
  id: string;
  name: string;
  location: {
    lat: number;
    lng: number;
    address: string;
  };
  contactNumber: string;
  email: string;
  operatingHours: {
    weekday: string;
    weekend: string;
  };
  services: string[];
  currentStock: {
    bloodType: BloodType;
    units: number;
    status: 'critical' | 'low' | 'adequate' | 'good';
  }[];
  verified: boolean;
  rating: number;
}