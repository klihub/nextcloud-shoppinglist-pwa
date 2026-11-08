// Package shoppinglist provides a small Go client for the Nextcloud Shopping List app API.
package shoppinglist

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"
)

const apiPath = "/index.php/apps/shoppinglist/api"

// Client talks to one Nextcloud Shopping List installation.
type Client struct {
	baseURL  string
	login    string
	password string
	http     *http.Client
}

// NewClient creates a client using a Nextcloud app password. serverURL is the
// Nextcloud installation URL, optionally including an installation subpath.
func NewClient(serverURL, loginName, appPassword string) (*Client, error) {
	return NewClientWithHTTPClient(serverURL, loginName, appPassword, &http.Client{Timeout: 30 * time.Second})
}

// NewClientWithHTTPClient is NewClient with a caller-supplied HTTP client.
func NewClientWithHTTPClient(serverURL, loginName, appPassword string, hc *http.Client) (*Client, error) {
	u, err := url.Parse(strings.TrimSpace(serverURL))
	if err != nil || u.Scheme == "" || u.Host == "" {
		return nil, fmt.Errorf("invalid Nextcloud server URL %q", serverURL)
	}
	if u.Scheme != "https" && u.Scheme != "http" {
		return nil, fmt.Errorf("unsupported URL scheme %q", u.Scheme)
	}
	if loginName == "" || appPassword == "" {
		return nil, fmt.Errorf("login name and app password are required")
	}
	if hc == nil {
		hc = http.DefaultClient
	}
	u.RawQuery, u.Fragment, u.User = "", "", nil
	u.Path = strings.TrimRight(u.Path, "/") + apiPath
	return &Client{baseURL: strings.TrimRight(u.String(), "/"), login: loginName, password: appPassword, http: hc}, nil
}

// Bool accepts the JSON boolean and 0/1 forms returned by database drivers.
type Bool bool

func (b *Bool) UnmarshalJSON(data []byte) error {
	var v bool
	if err := json.Unmarshal(data, &v); err == nil {
		*b = Bool(v)
		return nil
	}
	var n int
	if err := json.Unmarshal(data, &n); err == nil && (n == 0 || n == 1) {
		*b = Bool(n == 1)
		return nil
	}
	return fmt.Errorf("expected boolean or 0/1, got %s", data)
}

// List is a shopping list visible to the authenticated user.
type List struct {
	ID        int64  `json:"id"`
	Name      string `json:"name"`
	Owner     string `json:"owner"`
	Role      string `json:"role"`
	CreatedAt string `json:"created_at"`
	UpdatedAt string `json:"updated_at"`
}

// Item is an entry in a shopping list.
type Item struct {
	ID        int64  `json:"id"`
	ListID    int64  `json:"list_id"`
	Name      string `json:"name"`
	Quantity  *string `json:"quantity"`
	Note      *string `json:"note"`
	Category  *string `json:"category"`
	Checked   Bool   `json:"checked"`
	SortOrder int    `json:"sort_order"`
	CreatedBy string `json:"created_by"`
	CreatedAt string `json:"created_at"`
	UpdatedAt string `json:"updated_at"`
}

// Member is a user who can access a list.
type Member struct {
	UserID      string `json:"user_id"`
	DisplayName string `json:"display_name"`
	Role        string `json:"role"`
	CreatedAt   string `json:"created_at"`
}

// ItemInput contains fields accepted when creating an item.
type ItemInput struct {
	Name      string
	Quantity  *string
	Note      *string
	Category  *string
	SortOrder *int
}

// ItemUpdate contains optional fields to update. Pointer fields distinguish
// omitted values from explicit false or empty/null values.
type ItemUpdate struct {
	Name      *string
	Quantity  *string
	Note      *string
	Category  *string
	Checked   *bool
	SortOrder *int
}

// APIError describes a non-2xx response from the Nextcloud server.
type APIError struct {
	StatusCode int
	Message    string
}

func (e *APIError) Error() string {
	return fmt.Sprintf("shoppinglist API: HTTP %d: %s", e.StatusCode, e.Message)
}

func (c *Client) request(ctx context.Context, method, path string, form url.Values, out any) error {
	endpoint := c.baseURL + path
	var body io.Reader
	if form != nil {
		body = strings.NewReader(form.Encode())
	}
	req, err := http.NewRequestWithContext(ctx, method, endpoint, body)
	if err != nil {
		return err
	}
	req.SetBasicAuth(c.login, c.password)
	req.Header.Set("Accept", "application/json")
	// Nextcloud 30+ accepts this header on plain AppFramework controllers
	// as an API request, allowing app-password clients without browser CSRF tokens.
	req.Header.Set("OCS-APIRequest", "true")
	if form != nil {
		req.Header.Set("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8")
	}
	resp, err := c.http.Do(req)
	if err != nil {
		return fmt.Errorf("shoppinglist request: %w", err)
	}
	defer resp.Body.Close()
	data, err := io.ReadAll(io.LimitReader(resp.Body, 1<<20))
	if err != nil {
		return fmt.Errorf("read shoppinglist response: %w", err)
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		message := strings.TrimSpace(string(data))
		var payload struct { Error string `json:"error"` }
		if json.Unmarshal(data, &payload) == nil && payload.Error != "" {
			message = payload.Error
		}
		if message == "" {
			message = http.StatusText(resp.StatusCode)
		}
		return &APIError{StatusCode: resp.StatusCode, Message: message}
	}
	if out == nil || resp.StatusCode == http.StatusNoContent || len(data) == 0 {
		return nil
	}
	if err := json.Unmarshal(data, out); err != nil {
		return fmt.Errorf("decode shoppinglist response: %w", err)
	}
	return nil
}

func formString(v string) url.Values { return url.Values{"name": []string{v}} }

func (c *Client) ListLists(ctx context.Context) ([]List, error) {
	var result struct { Lists []List `json:"lists"` }
	err := c.request(ctx, http.MethodGet, "/lists", nil, &result)
	return result.Lists, err
}

func (c *Client) CreateList(ctx context.Context, name string) (List, error) {
	var result struct { List List `json:"list"` }
	err := c.request(ctx, http.MethodPost, "/lists", formString(name), &result)
	return result.List, err
}

func (c *Client) GetList(ctx context.Context, id int64) (List, error) {
	var result struct { List List `json:"list"` }
	err := c.request(ctx, http.MethodGet, "/lists/"+strconv.FormatInt(id, 10), nil, &result)
	return result.List, err
}

func (c *Client) DeleteList(ctx context.Context, id int64) error {
	return c.request(ctx, http.MethodDelete, "/lists/"+strconv.FormatInt(id, 10), nil, nil)
}

func (c *Client) ListMembers(ctx context.Context, listID int64) ([]Member, error) {
	var result struct { Members []Member `json:"members"` }
	err := c.request(ctx, http.MethodGet, listPath(listID)+"/members", nil, &result)
	return result.Members, err
}

func (c *Client) AddMember(ctx context.Context, listID int64, userID string) (Member, error) {
	var result struct { Member Member `json:"member"` }
	err := c.request(ctx, http.MethodPost, listPath(listID)+"/members", url.Values{"userId": []string{userID}}, &result)
	return result.Member, err
}

func (c *Client) RemoveMember(ctx context.Context, listID int64, userID string) error {
	return c.request(ctx, http.MethodDelete, listPath(listID)+"/members/"+url.PathEscape(userID), nil, nil)
}

func (c *Client) ListItems(ctx context.Context, listID int64) ([]Item, error) {
	var result struct { Items []Item `json:"items"` }
	err := c.request(ctx, http.MethodGet, listPath(listID)+"/items", nil, &result)
	return result.Items, err
}

func (c *Client) CreateItem(ctx context.Context, listID int64, item ItemInput) (Item, error) {
	form := url.Values{"name": []string{item.Name}}
	if item.Quantity != nil { form.Set("quantity", *item.Quantity) }
	if item.Note != nil { form.Set("note", *item.Note) }
	if item.Category != nil { form.Set("category", *item.Category) }
	if item.SortOrder != nil { form.Set("sort_order", strconv.Itoa(*item.SortOrder)) }
	var result struct { Item Item `json:"item"` }
	err := c.request(ctx, http.MethodPost, listPath(listID)+"/items", form, &result)
	return result.Item, err
}

func (c *Client) UpdateItem(ctx context.Context, listID, itemID int64, update ItemUpdate) (Item, error) {
	form := make(url.Values)
	if update.Name != nil { form.Set("name", *update.Name) }
	if update.Quantity != nil { form.Set("quantity", *update.Quantity) }
	if update.Note != nil { form.Set("note", *update.Note) }
	if update.Category != nil { form.Set("category", *update.Category) }
	if update.Checked != nil { form.Set("checked", strconv.FormatBool(*update.Checked)) }
	if update.SortOrder != nil { form.Set("sort_order", strconv.Itoa(*update.SortOrder)) }
	var result struct { Item Item `json:"item"` }
	err := c.request(ctx, http.MethodPut, listPath(listID)+"/items/"+strconv.FormatInt(itemID, 10), form, &result)
	return result.Item, err
}

func (c *Client) DeleteItem(ctx context.Context, listID, itemID int64) error {
	return c.request(ctx, http.MethodDelete, listPath(listID)+"/items/"+strconv.FormatInt(itemID, 10), nil, nil)
}

func listPath(id int64) string { return "/lists/" + strconv.FormatInt(id, 10) }

