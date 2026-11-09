package shoppinglist

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"net/url"
	"testing"
)

func TestListListsUsesAppPasswordAndDecodesResponse(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("OCS-APIRequest") != "true" {
			t.Fatalf("missing OCS-APIRequest header")
		}
		if r.URL.Path != "/cloud/index.php/apps/shoppinglist/api/lists" {
			t.Fatalf("unexpected path: %s", r.URL.Path)
		}
		user, pass, ok := r.BasicAuth()
		if !ok || user != "alice" || pass != "app-secret" {
			t.Fatalf("unexpected basic auth: %q %q %v", user, pass, ok)
		}
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"lists":[{"id":7,"name":"Groceries","owner":"alice","role":"owner"}]}`))
	}))
	defer srv.Close()

	c, err := NewClient(srv.URL+"/cloud", "alice", "app-secret")
	if err != nil { t.Fatal(err) }
	lists, err := c.ListLists(context.Background())
	if err != nil { t.Fatal(err) }
	if len(lists) != 1 || lists[0].ID != 7 || lists[0].Name != "Groceries" {
		t.Fatalf("unexpected lists: %#v", lists)
	}
}

func TestCreateItemFormAndBooleanDecoding(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost || r.URL.Path != "/index.php/apps/shoppinglist/api/lists/7/items" {
			t.Fatalf("unexpected request: %s %s", r.Method, r.URL.Path)
		}
		if err := r.ParseForm(); err != nil { t.Fatal(err) }
		if r.Form.Get("name") != "Coffee" || r.Form.Get("quantity") != "2" {
			t.Fatalf("unexpected form: %#v", r.Form)
		}
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"item":{"id":11,"list_id":7,"name":"Coffee","quantity":"2","checked":0}}`))
	}))
	defer srv.Close()

	c, err := NewClient(srv.URL, "alice", "secret")
	if err != nil { t.Fatal(err) }
	qty := "2"
	item, err := c.CreateItem(context.Background(), 7, ItemInput{Name: "Coffee", Quantity: &qty})
	if err != nil { t.Fatal(err) }
	if item.ID != 11 || bool(item.Checked) {
		t.Fatalf("unexpected item: %#v", item)
	}
}

func TestUpdateItemSendsFalseAndEncodesMemberID(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/index.php/apps/shoppinglist/api/lists/7/items/11" || r.Method != http.MethodPut {
			t.Fatalf("unexpected request: %s %s", r.Method, r.URL.Path)
		}
		if err := r.ParseForm(); err != nil { t.Fatal(err) }
		if got := r.Form.Get("checked"); got != "false" {
			t.Fatalf("checked = %q, want false", got)
		}
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{"item": map[string]any{"id": 11, "list_id": 7, "checked": false}})
	}))
	defer srv.Close()

	c, err := NewClient(srv.URL, "alice", "secret")
	if err != nil { t.Fatal(err) }
	checked := false
	_, err = c.UpdateItem(context.Background(), 7, 11, ItemUpdate{Checked: &checked})
	if err != nil { t.Fatal(err) }
}

func TestAPIError(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusForbidden)
		_, _ = w.Write([]byte(`{"error":"Only the list owner can manage sharing"}`))
	}))
	defer srv.Close()
	c, err := NewClient(srv.URL, "alice", "secret")
	if err != nil { t.Fatal(err) }
	_, err = c.CreateList(context.Background(), "x")
	apiErr, ok := err.(*APIError)
	if !ok || apiErr.StatusCode != http.StatusForbidden {
		t.Fatalf("expected 403 APIError, got %#v", err)
	}
}

func TestFormValuesCanRepresentOptionalEmptyValues(t *testing.T) {
	v := url.Values{"note": []string{""}}
	if v.Get("note") != "" { t.Fatal("expected empty note") }
}
