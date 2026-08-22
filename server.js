const express = require('express');
const fs = require('fs');
const path = require('path');
const app = express();

app.use(express.json());

// API Endpoint to append a new shipment
app.post('/api/shipments', (req, res) => {
  const newShipment = req.body;
  const filePath = path.join(__dirname, 'shipment.json');

  fs.readFile(filePath, 'utf8', (err, data) => {
    let shipments = {};
    if (!err && data) {
      try {
        shipments = JSON.parse(data);
      } catch (e) {
        shipments = {};
      }
    }

    // Key the object by its tracking ID
    shipments[newShipment.trackingId] = newShipment;

    fs.writeFile(filePath, JSON.stringify(shipments, null, 2), (writeErr) => {
      if (writeErr) {
        return res.status(500).json({ error: 'Failed to write to data store' });
      }
      res.status(200).json({ success: true, trackingId: newShipment.trackingId });
    });
  });
});