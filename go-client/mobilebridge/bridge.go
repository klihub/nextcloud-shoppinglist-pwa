// Package mobilebridge exposes a small gomobile-compatible API for Android.
// Keep this surface limited to primitive values and strings; the full Go API
// client remains in the parent package.
package mobilebridge

import (
	"context"
	"encoding/json"

	shoppinglist "github.com/klihub/nextcloud-shoppinglist-pwa/go-client"
)

// Client is the mobile-friendly facade over the Shopping List API client.
type Client struct {
	client *shoppinglist.Client
}

// NewClient creates an authenticated API client using a Nextcloud app password.
func OpenClient(serverURL, loginName, appPassword string) (*Client, error) {
	client, err := shoppinglist.NewClient(serverURL, loginName, appPassword)
	if err != nil {
		return nil, err
	}
	return &Client{client: client}, nil
}

// ListListsJSON returns the user's visible lists as JSON.
func (c *Client) ListListsJSON() (string, error) {
	lists, err := c.client.ListLists(context.Background())
	if err != nil {
		return "", err
	}
	data, err := json.Marshal(lists)
	if err != nil {
		return "", err
	}
	return string(data), nil
}

// ListItemsJSON returns the items in a list as JSON.
func (c *Client) ListItemsJSON(listID int64) (string, error) {
	items, err := c.client.ListItems(context.Background(), listID)
	if err != nil {
		return "", err
	}
	data, err := json.Marshal(items)
	if err != nil {
		return "", err
	}
	return string(data), nil
}
