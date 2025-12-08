# Knowledge Base API - OpenSearch Implementation

## Overview
Two controller modules for Knowledge Base:
1. **PublicKnowledgeBaseController** - Public access (no auth) for citizen portal
2. **KnowledgeBaseController** - Admin/Agent management (authenticated)

## Setup Requirements

### 1. Install Elasticsearch/OpenSearch PHP Client
```bash
cd crm-api
composer require elasticsearch/elasticsearch
```

### 2. Environment Configuration
Add to `.env`:
```env
OPENSEARCH_HOST=localhost:9200
# Or for AWS OpenSearch:
# OPENSEARCH_HOST=https://your-domain.region.es.amazonaws.com
```

### 3. Create OpenSearch Index
```bash
curl -X PUT "localhost:9200/knowledge_base" -H 'Content-Type: application/json' -d'
{
  "mappings": {
    "properties": {
      "title": { "type": "text" },
      "content": { "type": "text" },
      "category": { 
        "type": "text",
        "fields": {
          "keyword": { "type": "keyword" }
        }
      },
      "tags": { "type": "keyword" },
      "status": { "type": "keyword" },
      "views": { "type": "integer" },
      "helpful_count": { "type": "integer" },
      "not_helpful_count": { "type": "integer" },
      "author_id": { "type": "integer" },
      "author": { "type": "keyword" },
      "created_at": { "type": "date" },
      "updated_at": { "type": "date" }
    }
  }
}
'
```

---

## API Endpoints

### PUBLIC APIs (No Authentication) - `/api/v1/public/knowledge-base`

#### 1. Search Articles
```
GET /search?q={query}&category={category}&limit={limit}
```
**Example:**
```javascript
axios.get('/api/v1/public/knowledge-base/search', {
  params: { q: 'import duties', category: 'Customs', limit: 10 }
})
```

#### 2. Get Article (Auto-increment views)
```
GET /articles/{id}
```

#### 3. Get Popular Articles
```
GET /popular?limit={limit}
```

#### 4. Get Categories with Counts
```
GET /categories
```

#### 5. Submit Feedback
```
POST /articles/{id}/feedback
Body: { "helpful": true }
```

#### 6. Get FAQs
```
GET /faqs?limit={limit}
```

---

### ADMIN APIs (JWT Required) - `/api/v1/knowledge-base`

#### 1. List All Articles (with filters)
```
GET /knowledge-base?status={status}&category={category}&page={page}&per_page={per_page}
```
**Filters:**
- `status`: draft, published, archived
- `category`: string
- `author_id`: integer
- `page`: pagination (default 1)
- `per_page`: items per page (default 20, max 100)

#### 2. Create Article
```
POST /knowledge-base
Body: {
  "title": "How to Calculate Import Duties",
  "content": "Full article content...",
  "category": "Import Duties",
  "tags": ["customs", "tax", "calculation"],
  "status": "draft"  // or "published"
}
```

#### 3. Get Single Article
```
GET /knowledge-base/{id}
```

#### 4. Update Article
```
PUT /knowledge-base/{id}
Body: {
  "title": "Updated title",
  "content": "Updated content",
  "status": "published"
}
```

#### 5. Delete Article
```
DELETE /knowledge-base/{id}
```

#### 6. Bulk Import
```
POST /knowledge-base/bulk-import
Body: {
  "articles": [
    {
      "title": "Article 1",
      "content": "Content 1",
      "category": "FAQ",
      "tags": ["tag1"],
      "status": "published"
    },
    {
      "title": "Article 2",
      "content": "Content 2",
      "category": "Customs",
      "tags": ["tag2"],
      "status": "draft"
    }
  ]
}
```

#### 7. Get Statistics
```
GET /knowledge-base-statistics
```
**Returns:**
- Total articles count
- Total views
- Average helpful rating
- Articles by status
- Articles by category

---

## Frontend Integration Examples

### Public Portal (Citizen)

**File:** `src/pages/citizen-self-service-portal/components/KnowledgeBaseSearch.jsx`

```javascript
import axios from 'axios';

// Search articles
const searchArticles = async (query, category) => {
  try {
    const response = await axios.get('/api/v1/public/knowledge-base/search', {
      params: { q: query, category, limit: 20 }
    });
    return response.data.data; // Array of articles
  } catch (error) {
    console.error('Search failed:', error);
    return [];
  }
};

// Load popular articles
const loadPopular = async () => {
  const response = await axios.get('/api/v1/public/knowledge-base/popular', {
    params: { limit: 10 }
  });
  return response.data.data;
};

// View article (increments view count)
const viewArticle = async (id) => {
  const response = await axios.get(`/api/v1/public/knowledge-base/articles/${id}`);
  return response.data.data;
};

// Submit feedback
const submitFeedback = async (articleId, isHelpful) => {
  await axios.post(`/api/v1/public/knowledge-base/articles/${articleId}/feedback`, {
    helpful: isHelpful
  });
};

// Load categories
const loadCategories = async () => {
  const response = await axios.get('/api/v1/public/knowledge-base/categories');
  return response.data.data; // [{ name: "FAQ", count: 15 }, ...]
};
```

---

### Admin Panel (Knowledge Base Management)

**Create new component:** `src/pages/knowledge-base-management/`

```javascript
import axios from 'axios';

// List articles with filters
const listArticles = async (filters = {}) => {
  const response = await axios.get('/api/v1/knowledge-base', {
    params: {
      status: filters.status,
      category: filters.category,
      page: filters.page || 1,
      per_page: filters.perPage || 20
    }
  });
  return response.data; // { data: [...], pagination: {...} }
};

// Create article
const createArticle = async (articleData) => {
  const response = await axios.post('/api/v1/knowledge-base', {
    title: articleData.title,
    content: articleData.content,
    category: articleData.category,
    tags: articleData.tags,
    status: articleData.status || 'draft'
  });
  return response.data;
};

// Update article
const updateArticle = async (id, updates) => {
  await axios.put(`/api/v1/knowledge-base/${id}`, updates);
};

// Delete article
const deleteArticle = async (id) => {
  await axios.delete(`/api/v1/knowledge-base/${id}`);
};

// Bulk import
const bulkImport = async (articles) => {
  const response = await axios.post('/api/v1/knowledge-base/bulk-import', {
    articles: articles
  });
  return response.data;
};

// Get statistics
const getStatistics = async () => {
  const response = await axios.get('/api/v1/knowledge-base-statistics');
  return response.data.data;
};
```

---

## Sample Data Structure

### Article Object (Response)
```json
{
  "id": "abc123",
  "title": "How to Calculate Import Duties",
  "content": "Full article content with markdown or HTML...",
  "excerpt": "First 200 characters...",
  "category": "Import Duties",
  "tags": ["customs", "tax", "calculation"],
  "status": "published",
  "views": 1523,
  "helpful_count": 45,
  "not_helpful_count": 3,
  "author_id": 1,
  "author": "Admin User",
  "created_at": "2025-01-15T10:30:00Z",
  "updated_at": "2025-02-20T14:22:00Z"
}
```

### Search Response
```json
{
  "success": true,
  "data": [
    {
      "id": "abc123",
      "title": "...",
      "excerpt": "...",
      "category": "...",
      "tags": [...],
      "views": 1523,
      "helpful_count": 45,
      "score": 4.523  // Relevance score
    }
  ],
  "total": 45,
  "query": "import duties"
}
```

### Categories Response
```json
{
  "success": true,
  "data": [
    { "name": "Import Duties", "count": 23 },
    { "name": "Customs Clearance", "count": 18 },
    { "name": "FAQ", "count": 45 }
  ]
}
```

---

## Recommended Categories for Seeding

1. **FAQ** - Frequently Asked Questions
2. **Import Duties** - Duty calculation, rates, exemptions
3. **Customs Clearance** - Clearance procedures, documents
4. **Tax Calculation** - Tax rates, computation methods
5. **Documentation** - Required forms, certificates
6. **Prohibited Items** - Restricted/banned goods
7. **Shipping & Logistics** - Delivery, tracking
8. **Regulations** - Laws, compliance requirements

---

## Next Steps

### Frontend TODO:

1. **Update KnowledgeBaseSearch.jsx** (Citizen Portal)
   - Remove mockArticles array
   - Replace with API calls to public endpoints
   - Add category filter dropdown
   - Add "Was this helpful?" buttons

2. **Create Knowledge Base Management Module** (Admin)
   - New page: `src/pages/knowledge-base-management/`
   - Components:
     * `ArticleList.jsx` - List/filter articles
     * `ArticleEditor.jsx` - Create/edit form (rich text editor)
     * `ArticlePreview.jsx` - Preview before publish
     * `BulkImport.jsx` - CSV/JSON import interface
     * `Statistics.jsx` - Dashboard with charts

3. **Add to Navigation** (for Admin/Supervisor roles)
   - Add "Knowledge Base" menu item in sidebar
   - Icon: 📚 or 📖
   - Route: `/knowledge-base-management`

---

## Rich Text Editor Recommendations

For article content editing, consider:

1. **TinyMCE** (Free tier available)
   ```bash
   npm install @tinymce/tinymce-react
   ```

2. **Quill** (Open source)
   ```bash
   npm install react-quill
   ```

3. **Draft.js** (By Meta/Facebook)
   ```bash
   npm install draft-js react-draft-wysiwyg
   ```

---

## Testing OpenSearch Connection

```bash
# Check if OpenSearch is running
curl http://localhost:9200

# Check index exists
curl http://localhost:9200/knowledge_base

# Search all documents
curl http://localhost:9200/knowledge_base/_search?pretty

# Get document by ID
curl http://localhost:9200/knowledge_base/_doc/{id}
```

---

## Security Notes

✅ **Public endpoints** - No authentication, rate limiting recommended
✅ **Admin endpoints** - JWT authentication required via `jwt.verify` middleware
✅ **Input validation** - All requests validated with Laravel validators
✅ **XSS protection** - Use `strip_tags()` for excerpts, sanitize content on frontend
✅ **Author tracking** - Automatically logs user ID and name on creation

---

## Performance Optimization

1. **Pagination** - Default 20 items, max 100 per page
2. **Fuzzy search** - `fuzziness: 'AUTO'` for typo tolerance
3. **Field boosting** - Title^3, Tags^2 weighted higher than content
4. **Excerpt generation** - Only return 200 chars in list views
5. **View counter** - Async update, doesn't block response
6. **Caching** - Consider Redis cache for popular articles

---

Ready to integrate! 🚀
