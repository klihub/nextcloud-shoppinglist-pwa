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

// OpenClient creates an authenticated API client using a Nextcloud app password.
func OpenClient(serverURL, loginName, appPassword string) (*Client, error) {
	client, err := shoppinglist.NewClient(serverURL, loginName, appPassword)
	if err != nil {
		return nil, err
	}
	return &Client{client: client}, nil
}

func encode(value any) (string, error) {
	data, err := json.Marshal(value)
	if err != nil {
		return "", err
	}
	return string(data), nil
}

// ListListsJSON returns the user's visible lists as a JSON array.
func (c *Client) ListListsJSON() (string, error) {
	lists, err := c.client.ListLists(context.Background())
	if err != nil {
		return "", err
	}
	return encode(lists)
}

// ListItemsJSON returns the items in a list as a JSON array.
func (c *Client) ListItemsJSON(listID int64) (string, error) {
	items, err := c.client.ListItems(context.Background(), listID)
	if err != nil {
		return "", err
	}
	return encode(items)
}

// CreateListJSON creates a list and returns the created list as JSON.
func (c *Client) CreateListJSON(name string) (string, error) {
	list, err := c.client.CreateList(context.Background(), name)
	if err != nil {
		return "", err
	}
	return encode(list)
}

// DeleteList deletes a list.
func (c *Client) DeleteList(listID int64) error {
	return c.client.DeleteList(context.Background(), listID)
}

// CreateItemJSON creates an item and returns it as JSON.
func (c *Client) CreateItemJSON(listID int64, name string) (string, error) {
	item, err := c.client.CreateItem(context.Background(), listID, shoppinglist.ItemInput{Name: name})
	if err != nil {
		return "", err
	}
	return encode(item)
}

// UpdateItemCheckedJSON changes an item's checked state and returns it as JSON.
func (c *Client) UpdateItemCheckedJSON(listID, itemID int64, checked bool) (string, error) {
	item, err := c.client.UpdateItem(context.Background(), listID, itemID, shoppinglist.ItemUpdate{Checked: &checked})
	if err != nil {
		return "", err
	}
	return encode(item)
}

// DeleteItem deletes an item from a list.
func (c *Client) DeleteItem(listID, itemID int64) error {
	return c.client.DeleteItem(context.Background(), listID, itemID)
}
