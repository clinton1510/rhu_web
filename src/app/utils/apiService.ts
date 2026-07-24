/**
 * API Service Layer for RHU Frontend
 * Handles all communication with the backend API
 */

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:5000/api';

// Helper function for API requests
async function fetchAPI(endpoint: string, options: RequestInit = {}) {
  const token = localStorage.getItem('authToken');
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    ...options.headers,
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...options,
    headers,
  });

  const rawBody = await response.text();
  const payload = rawBody ? JSON.parse(rawBody) : null;

  if (!response.ok) {
    throw new Error(payload?.message || payload?.error || 'API request failed');
  }

  return payload;
}

// ============================================
// AUTHENTICATION API
// ============================================

export const authAPI = {
  login: (email: string, password: string) =>
    fetchAPI('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, username: email, password }),
    }),

  register: (userData: any) =>
    fetchAPI('/auth/register', {
      method: 'POST',
      body: JSON.stringify(userData),
    }),

  logout: () => {
    localStorage.removeItem('authToken');
    localStorage.removeItem('user');
  },

  getCurrentUser: () => {
    const user = localStorage.getItem('user');
    return user ? JSON.parse(user) : null;
  },

  setAuthToken: (token: string | null, user: any) => {
    if (token) {
      localStorage.setItem('authToken', token);
    }
    localStorage.setItem('user', JSON.stringify(user));
  },
};

// ============================================
// RESIDENTS API
// ============================================

export const residentsAPI = {
  getAll: (limit = 100) =>
    fetchAPI(`/residents?limit=${limit}`),

  getById: (id: number) =>
    fetchAPI(`/residents/${id}`),

  getByEmail: (email: string) =>
    fetchAPI(`/residents/email/${encodeURIComponent(email)}`),

  create: (residentData: any) =>
    fetchAPI('/residents', {
      method: 'POST',
      body: JSON.stringify(residentData),
    }),

  update: (id: number, residentData: any) =>
    fetchAPI(`/residents/${id}`, {
      method: 'PUT',
      body: JSON.stringify(residentData),
    }),

  delete: (id: number) =>
    fetchAPI(`/residents/${id}`, {
      method: 'DELETE',
    }),

  getByBarangay: (barangay: string) =>
    fetchAPI(`/residents/barangay/${encodeURIComponent(barangay)}`),

  search: (query: string) =>
    fetchAPI(`/residents/search?q=${encodeURIComponent(query)}`),
};

// ============================================
// DONORS API
// ============================================

export const donorsAPI = {
  getAll: () =>
    fetchAPI('/donors'),

  getById: (id: number) =>
    fetchAPI(`/donors/${id}`),

  getByBloodType: (bloodType: string) =>
    fetchAPI(`/donors/blood-type/${bloodType}`),

  getAvailable: () =>
    fetchAPI('/donors/available'),

  register: (donorData: any) =>
    fetchAPI('/donors', {
      method: 'POST',
      body: JSON.stringify(donorData),
    }),

  update: (id: number, donorData: any) =>
    fetchAPI(`/donors/${id}`, {
      method: 'PUT',
      body: JSON.stringify(donorData),
    }),

  recordDonation: (donorId: number, donationData: any) =>
    fetchAPI(`/donors/${donorId}/donations`, {
      method: 'POST',
      body: JSON.stringify(donationData),
    }),
};

// ============================================
// BLOOD INVENTORY API
// ============================================

export const bloodInventoryAPI = {
  getAll: () =>
    fetchAPI('/blood-inventory'),

  getByBloodType: (bloodType: string) =>
    fetchAPI(`/blood-inventory/blood-type/${bloodType}`),

  update: (bloodBankId: number, bloodType: string, quantity: number) =>
    fetchAPI(`/blood-inventory/${bloodBankId}`, {
      method: 'PUT',
      body: JSON.stringify({ bloodType, quantity }),
    }),
};

// ============================================
// BLOOD REQUESTS API
// ============================================

export const bloodRequestsAPI = {
  getAll: () =>
    fetchAPI('/blood-requests'),

  getById: (id: number) =>
    fetchAPI(`/blood-requests/${id}`),

  create: (requestData: any) =>
    fetchAPI('/blood-requests', {
      method: 'POST',
      body: JSON.stringify(requestData),
    }),

  findMatches: (requestId: number) =>
    fetchAPI(`/blood-requests/${requestId}/matches`),

  updateStatus: (id: number, status: string) =>
    fetchAPI(`/blood-requests/${id}`, {
      method: 'PUT',
      body: JSON.stringify({ status }),
    }),
};

// ============================================
// BLOOD BANKS API
// ============================================

export const bloodBanksAPI = {
  getAll: () =>
    fetchAPI('/blood-banks'),

  getById: (id: number) =>
    fetchAPI(`/blood-banks/${id}`),

  getNearby: (lat: number, lng: number, radius: number = 10) =>
    fetchAPI(`/blood-banks/nearby?lat=${lat}&lng=${lng}&radius=${radius}`),
};

// ============================================
// CONSULTATIONS API
// ============================================

export const consultationsAPI = {
  getAll: () =>
    fetchAPI('/consultations'),

  getByResidentId: (residentId: number) =>
    fetchAPI(`/consultations/resident/${residentId}`),

  create: (consultationData: any) =>
    fetchAPI('/consultations', {
      method: 'POST',
      body: JSON.stringify(consultationData),
    }),

  update: (id: number, consultationData: any) =>
    fetchAPI(`/consultations/${id}`, {
      method: 'PUT',
      body: JSON.stringify(consultationData),
    }),
};

// ============================================
// STAFF API
// ============================================

export const staffAPI = {
  getAll: () =>
    fetchAPI('/staff'),

  getByType: (staffType: string) =>
    fetchAPI(`/staff/type/${staffType}`),

  getByBarangay: (barangay: string) =>
    fetchAPI(`/staff/barangay/${encodeURIComponent(barangay)}`),

  getBHWByBarangay: (barangay: string) =>
    fetchAPI(`/staff/bhw/barangay/${encodeURIComponent(barangay)}`),
};

// ============================================
// VACCINATION API
// ============================================

export const vaccinationAPI = {
  getSchedules: () =>
    fetchAPI('/vaccination/schedules'),

  getRecords: (residentId: number) =>
    fetchAPI(`/vaccination/records/resident/${residentId}`),

  recordVaccination: (vaccinationData: any) =>
    fetchAPI('/vaccination/records', {
      method: 'POST',
      body: JSON.stringify(vaccinationData),
    }),
};

// ============================================
// PREGNANCY & MATERNAL HEALTH API
// ============================================

export const maternalHealthAPI = {
  getPregnancies: () =>
    fetchAPI('/maternal/pregnancies'),

  getPregnancyByResident: (residentId: number) =>
    fetchAPI(`/maternal/pregnancies/resident/${residentId}`),

  createPregnancy: (pregnancyData: any) =>
    fetchAPI('/maternal/pregnancies', {
      method: 'POST',
      body: JSON.stringify(pregnancyData),
    }),

  recordPrenatalVisit: (pregnancyId: number, visitData: any) =>
    fetchAPI(`/maternal/pregnancies/${pregnancyId}/visits`, {
      method: 'POST',
      body: JSON.stringify(visitData),
    }),

  recordDelivery: (pregnancyId: number, deliveryData: any) =>
    fetchAPI(`/maternal/pregnancies/${pregnancyId}/delivery`, {
      method: 'POST',
      body: JSON.stringify(deliveryData),
    }),
};

// ============================================
// DISEASE SURVEILLANCE (PIDSR) API
// ============================================

export const diseaseSurveillanceAPI = {
  getDiseaseList: () =>
    fetchAPI('/disease/types'),

  reportCase: (caseData: any) =>
    fetchAPI('/disease/cases', {
      method: 'POST',
      body: JSON.stringify(caseData),
    }),

  getCases: (filters?: any) => {
    const query = filters ? `?${new URLSearchParams(filters).toString()}` : '';
    return fetchAPI(`/disease/cases${query}`);
  },

  getCasesByDate: (startDate: string, endDate: string) =>
    fetchAPI(`/disease/cases?startDate=${startDate}&endDate=${endDate}`),
};

// ============================================
// TB-DOTS API
// ============================================

export const tbDotsAPI = {
  getTBPatients: () =>
    fetchAPI('/tb-dots/patients'),

  createTBPatient: (patientData: any) =>
    fetchAPI('/tb-dots/patients', {
      method: 'POST',
      body: JSON.stringify(patientData),
    }),

  recordAdherence: (tbPatientId: number, adherenceData: any) =>
    fetchAPI(`/tb-dots/patients/${tbPatientId}/adherence`, {
      method: 'POST',
      body: JSON.stringify(adherenceData),
    }),
};

// ============================================
// VITAL STATISTICS API
// ============================================

export const vitalStatsAPI = {
  registerBirth: (birthData: any) =>
    fetchAPI('/vital-stats/births', {
      method: 'POST',
      body: JSON.stringify(birthData),
    }),

  registerDeath: (deathData: any) =>
    fetchAPI('/vital-stats/deaths', {
      method: 'POST',
      body: JSON.stringify(deathData),
    }),

  getMonthlyStats: (month: number, year: number) =>
    fetchAPI(`/vital-stats?month=${month}&year=${year}`),
};

// ============================================
// CERTIFICATES API
// ============================================

export const certificatesAPI = {
  getTypes: () =>
    fetchAPI('/certificates/types'),

  issueCertificate: (certificateData: any) =>
    fetchAPI('/certificates', {
      method: 'POST',
      body: JSON.stringify(certificateData),
    }),

  getResidentCertificates: (residentId: number) =>
    fetchAPI(`/certificates/resident/${residentId}`),

  verifyCertificate: (certificateNumber: string) =>
    fetchAPI(`/certificates/verify/${certificateNumber}`),
};

// ============================================
// ANALYTICS & REPORTS API
// ============================================

export const analyticsAPI = {
  getDashboardStats: () =>
    fetchAPI('/analytics/dashboard'),

  getBarangayStats: (barangay: string) =>
    fetchAPI(`/analytics/barangay/${encodeURIComponent(barangay)}`),

  getHealthIndicators: () =>
    fetchAPI('/analytics/indicators'),

  getFHSISReport: (month: number, year: number) =>
    fetchAPI(`/analytics/fhsis?month=${month}&year=${year}`),

  getPIDSRReport: (week: number, year: number) =>
    fetchAPI(`/analytics/pidsr?week=${week}&year=${year}`),
};

// ============================================
// BARANGAYS API
// ============================================

export const barangaysAPI = {
  getAll: () =>
    fetchAPI('/barangays'),

  getByName: (name: string) =>
    fetchAPI(`/barangays/${encodeURIComponent(name)}`),
};

export default {
  auth: authAPI,
  residents: residentsAPI,
  donors: donorsAPI,
  bloodInventory: bloodInventoryAPI,
  bloodRequests: bloodRequestsAPI,
  bloodBanks: bloodBanksAPI,
  consultations: consultationsAPI,
  staff: staffAPI,
  vaccination: vaccinationAPI,
  maternalHealth: maternalHealthAPI,
  diseaseSurveillance: diseaseSurveillanceAPI,
  tbDots: tbDotsAPI,
  vitalStats: vitalStatsAPI,
  certificates: certificatesAPI,
  analytics: analyticsAPI,
  barangays: barangaysAPI,
};
