const express = require('express');
const axios = require('axios');
require('dotenv').config();

const app = express();
app.use(express.json());

// API Credentials from environment variables
const CASS_CODE = process.env.IATA_CASS_CODE || '10423837';
const API_KEY = process.env.CARGO_API_KEY;
const API_URL = process.env.CARGO_API_URL || 'https://api.cargo-provider.com/v1';

// ----------------------------------------------------
// ROUTE 1: Search Rates & Availability
// ----------------------------------------------------
app.post('/api/cargo/search', async (req, res) => {
  try {
    const { origin, destination, weight, volume } = req.body;

    const response = await axios.post(
      `${API_URL}/rates/search`,
      {
        cassNumber: CASS_CODE,
        originAirport: origin,       // e.g. "HGA"
        destinationAirport: destination, // e.g. "DXB"
        chargeableWeightKg: weight,
        volumeCbm: volume
      },
      {
        headers: {
          'x-api-key': API_KEY,
          'Content-Type': 'application/json'
        }
      }
    );

    res.json({ success: true, data: response.data });
  } catch (error) {
    res.status(500).json({ 
      success: false, 
      error: error.response?.data || 'Failed to search cargo rates' 
    });
  }
});

// ----------------------------------------------------
// ROUTE 2: Create Air Cargo Booking
// ----------------------------------------------------
app.post('/api/cargo/book', async (req, res) => {
  try {
    const { flightId, shipper, consignee, cargoDetails } = req.body;

    const response = await axios.post(
      `${API_URL}/bookings/create`,
      {
        cassNumber: CASS_CODE,
        flightId,
        shipper,
        consignee,
        cargoDetails
      },
      {
        headers: {
          'x-api-key': API_KEY,
          'Content-Type': 'application/json'
        }
      }
    );

    res.json({ success: true, booking: response.data });
  } catch (error) {
    res.status(500).json({ 
      success: false, 
      error: error.response?.data || 'Failed to create booking' 
    });
  }
});

// ----------------------------------------------------
// ROUTE 3: Track Shipment Status by MAWB
// ----------------------------------------------------
app.get('/api/cargo/track/:awb', async (req, res) => {
  try {
    const awbNumber = req.params.awb;

    const response = await axios.get(
      `${API_URL}/shipments/track/${awbNumber}`,
      {
        headers: { 'x-api-key': API_KEY }
      }
    );

    res.json({ success: true, tracking: response.data });
  } catch (error) {
    res.status(500).json({ 
      success: false, 
      error: error.response?.data || 'Failed to fetch tracking data' 
    });
  }
});

// Start Application Server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Single-file Cargo Server active on port ${PORT}`);
});