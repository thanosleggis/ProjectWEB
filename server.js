const express = require('express');
const mysql = require('mysql2');
const cors = require('cors');
const path = require('path');
const multer = require('multer');

const app = express();
const PORT = 3000;

app.use(cors());
app.use(express.json());

const db = mysql.createConnection({
    host: '127.0.0.1',       // Your MySQL host (use remote address if needed)
    user: 'root',            // Your MySQL username
    password: '123456',            // Your MySQL password
    database: 'projectweb', // Your MySQL database name
});

db.connect((err) => {
    if (err) {
        console.error('Error connecting to the database:', err.message);
    } else {
        console.log('Connected to the MySQL database!');
    }
});

// Serve static files from the public directory
app.use(express.static(path.join(__dirname, 'public')));

// Handle requests to the home route
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// Configure multer for file uploads
const upload = multer({
    dest: path.join(__dirname, 'uploads'), // Destination folder for uploads
    limits: { fileSize: 5 * 1024 * 1024 }, // Limit file size to 5MB
});

// Endpoint to handle file uploads
app.post('/upload', upload.single('file'), (req, res) => {
    if (!req.file) {
        return res.status(400).json({ error: 'No file uploaded' });
    }
    res.status(200).json({ message: 'File uploaded successfully', filePath: `/uploads/${req.file.filename}` });
});

app.listen(PORT, () => {
    console.log(`Server is running at http://localhost:${PORT}`);
});