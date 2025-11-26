# Language API Documentation

Base URL: `http://localhost:8000/api/v1`

## Endpoints

### 1. Get Available Languages
Get a list of all supported languages.

**Endpoint:** `GET /languages`

**Authentication:** None (Public)

**Response:**
```json
{
  "success": true,
  "languages": [
    {
      "code": "en",
      "name": "English",
      "nativeName": "English",
      "flag": "🇺🇸"
    },
    {
      "code": "ms",
      "name": "Malay",
      "nativeName": "Bahasa Malaysia",
      "flag": "🇲🇾"
    }
  ]
}
```

---

### 2. Get Translations for Specific Language
Get all translations for a specific locale.

**Endpoint:** `GET /translations/{locale}`

**Parameters:**
- `locale` (optional): Language code (en, ms). Default: en

**Authentication:** None (Public)

**Example Request:**
```
GET /translations/en
GET /translations/ms
```

**Response:**
```json
{
  "success": true,
  "locale": "en",
  "translations": {
    "login": {
      "username": "Username",
      "password": "Password",
      "signIn": "Sign In",
      ...
    },
    "common": {
      "loading": "Loading...",
      "save": "Save",
      ...
    },
    "dashboard": {
      "title": "Agent Dashboard",
      ...
    }
  }
}
```

**Error Response (Invalid Locale):**
```json
{
  "success": false,
  "message": "Invalid language code. Supported languages: en, ms"
}
```

---

### 3. Get All Translations
Get translations for all supported languages at once.

**Endpoint:** `GET /translations`

**Authentication:** None (Public)

**Response:**
```json
{
  "success": true,
  "translations": {
    "en": {
      "login": { ... },
      "common": { ... },
      "dashboard": { ... }
    },
    "ms": {
      "login": { ... },
      "common": { ... },
      "dashboard": { ... }
    }
  }
}
```

---

## Usage Examples

### JavaScript/TypeScript (using languageService)

```typescript
import { languageService } from './services/languageService';

// Get available languages
const languages = await languageService.getLanguages();

// Get English translations
const enTranslations = await languageService.getTranslations('en');

// Get Malay translations
const msTranslations = await languageService.getTranslations('ms');

// Get all translations
const allTranslations = await languageService.getAllTranslations();
```

### React Component Example

```jsx
import { useEffect, useState } from 'react';
import { languageService } from './services/languageService';

function MyComponent() {
  const [translations, setTranslations] = useState({});
  const [locale, setLocale] = useState('en');

  useEffect(() => {
    const loadTranslations = async () => {
      const data = await languageService.getTranslations(locale);
      setTranslations(data.translations);
    };
    loadTranslations();
  }, [locale]);

  return (
    <div>
      <h1>{translations?.login?.title}</h1>
      <button onClick={() => setLocale(locale === 'en' ? 'ms' : 'en')}>
        Switch Language
      </button>
    </div>
  );
}
```

---

## Translation Structure

The translations are organized into namespaces:

- **login**: Login page translations
- **common**: Common UI elements (buttons, labels, etc.)
- **dashboard**: Dashboard-specific translations
- **ticket**: Ticket management translations

### Adding New Translations

1. Edit `/lang/en.json` for English
2. Edit `/lang/ms.json` for Malay
3. Follow the existing structure
4. Restart Laravel server if needed

Example:
```json
{
  "myModule": {
    "title": "My Title",
    "description": "My Description"
  }
}
```

---

## Supported Languages

| Code | Language | Native Name |
|------|----------|-------------|
| en   | English  | English     |
| ms   | Malay    | Bahasa Malaysia |
