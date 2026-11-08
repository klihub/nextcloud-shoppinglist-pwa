// Command integration-test exercises the Shopping List API against a live Nextcloud server.
// It creates a uniquely named temporary list and removes it when finished.
package main

import (
	"context"
	"fmt"
	"os"
	"strings"
	"time"

	shoppinglist "github.com/klihub/nextcloud-shoppinglist-pwa/go-client"
)

func main() {
	if err := run(); err != nil {
		fmt.Fprintln(os.Stderr, "FAIL:", err)
		os.Exit(1)
	}
}

func run() error {
	serverURL := strings.TrimSpace(os.Getenv("NEXTCLOUD_URL"))
	login := os.Getenv("NEXTCLOUD_USER")
	password := os.Getenv("NEXTCLOUD_APP_PASSWORD")
	if serverURL == "" || login == "" || password == "" {
		return fmt.Errorf("set NEXTCLOUD_URL, NEXTCLOUD_USER, and NEXTCLOUD_APP_PASSWORD")
	}

	client, err := shoppinglist.NewClient(serverURL, login, password)
	if err != nil {
		return fmt.Errorf("configure client: %w", err)
	}

	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Minute)
	defer cancel()

	lists, err := client.ListLists(ctx)
	if err != nil {
		return fmt.Errorf("list existing lists: %w", err)
	}
	fmt.Printf("Authentication OK; %d existing list(s) visible.\n", len(lists))

	list, err := client.CreateList(ctx, fmt.Sprintf("API integration test %d", time.Now().UnixNano()))
	if err != nil {
		return fmt.Errorf("create temporary list: %w", err)
	}
	fmt.Printf("Created temporary list %d.\n", list.ID)

	var itemID int64
	defer func() {
		cleanupCtx, cleanupCancel := context.WithTimeout(context.Background(), 15*time.Second)
		defer cleanupCancel()
		if itemID != 0 {
			if err := client.DeleteItem(cleanupCtx, list.ID, itemID); err != nil {
				fmt.Fprintf(os.Stderr, "Cleanup: could not delete item %d: %v\n", itemID, err)
			}
		}
		if err := client.DeleteList(cleanupCtx, list.ID); err != nil {
			fmt.Fprintf(os.Stderr, "Cleanup: could not delete temporary list %d: %v\n", list.ID, err)
		} else {
			fmt.Printf("Removed temporary list %d.\n", list.ID)
		}
	}()

	gotList, err := client.GetList(ctx, list.ID)
	if err != nil {
		return fmt.Errorf("get temporary list: %w", err)
	}
	if gotList.ID != list.ID {
		return fmt.Errorf("verify temporary list: got ID %d, want %d", gotList.ID, list.ID)
	}

	members, err := client.ListMembers(ctx, list.ID)
	if err != nil {
		return fmt.Errorf("list members: %w", err)
	}
	fmt.Printf("List read OK; %d member(s).\n", len(members))

	quantity, note, category := "2", "created by Go integration test", "test"
	item, err := client.CreateItem(ctx, list.ID, shoppinglist.ItemInput{
		Name: "Temporary integration-test item",
		Quantity: &quantity,
		Note: &note,
		Category: &category,
	})
	if err != nil {
		return fmt.Errorf("create item: %w", err)
	}
	itemID = item.ID
	fmt.Printf("Created item %d.\n", item.ID)

	items, err := client.ListItems(ctx, list.ID)
	if err != nil {
		return fmt.Errorf("list items: %w", err)
	}
	if len(items) != 1 || items[0].ID != item.ID {
		return fmt.Errorf("verify item listing: expected only item %d, got %d item(s)", item.ID, len(items))
	}

	checked := true
	item, err = client.UpdateItem(ctx, list.ID, item.ID, shoppinglist.ItemUpdate{Checked: &checked})
	if err != nil {
		return fmt.Errorf("check item: %w", err)
	}
	if !bool(item.Checked) {
		return fmt.Errorf("verify checked item: server returned checked=false")
	}

	checked = false
	item, err = client.UpdateItem(ctx, list.ID, item.ID, shoppinglist.ItemUpdate{Checked: &checked})
	if err != nil {
		return fmt.Errorf("uncheck item: %w", err)
	}
	if bool(item.Checked) {
		return fmt.Errorf("verify unchecked item: server returned checked=true")
	}

	if err := client.DeleteItem(ctx, list.ID, item.ID); err != nil {
		return fmt.Errorf("delete item: %w", err)
	}
	itemID = 0
	fmt.Println("Item create/read/check/uncheck/delete OK.")
	fmt.Println("All API integration checks passed.")
	return nil
}
