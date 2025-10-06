FROM python:3.12.8-slim

# Set environment variables
ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

WORKDIR /app

# Install system dependencies needed for psycopg2 and other packages
RUN apt-get clean && apt-get update --allow-releaseinfo-change && apt-get install -y --no-install-recommends \
    libpq-dev \
    build-essential \
    gcc \
    g++ \
    && rm -rf /var/lib/apt/lists/*

# Upgrade pip first
RUN pip install --upgrade pip

# Copy and install requirements
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application code
COPY . .

EXPOSE 9998
CMD ["python", "api.py"]
