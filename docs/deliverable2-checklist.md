# Deliverable 2 checklist

| Criteria | Marks | Status |
| --- | --- | --- |
| 2.1 Introduction | 5 | To write in the report |
| 2.2 Prototyping — Main website | 6 | Built: screenshots of home, catalogue, book, cart, checkout, account on phone/tablet/desktop |
| 2.2 Prototyping — Admin website | 6 | Built: screenshots of dashboard, orders, order view, books, inventory, stores, accounts |
| 2.3 CRC cards | 3 | Later |
| 2.3 EERD | 3 | Later — UserAccount supertype with disjoint, total subtypes Customer / Staff / Admin |
| 2.3 Context diagram | 3 | Later |
| 2.3 DFD | 3 | Later |
| 2.3 Use case diagram | 3 | Later |
| 2.4 Sample PHP / HTML / JS / CSS | 12 | See "Code samples" in README.md |
| 2.4 MySQL table screenshots | 2 | Screenshot tables in the TiDB SQL Editor after running database/dbSetup.sql |
| 2.5 Main website functionality | 8 | Done |
| 2.5 Admin website functionality | 8 | Done |
| 2.6 Conclusion | 3 | To write in the report |

## Entities for the diagrams

- **Actors:** Customer, Staff member, Administrator (plus the courier/bank as external entities for the context diagram)
- **Entities:** UserAccount, Customer, Staff, Admin, Store, Category, Book, Inventory, CustomerOrder, OrderItem, OrderStatusHistory, StockMovement, ContactMessage, ActivityLog
- **Key relationships:** Book 1..* Inventory *..1 Store · Customer 1..* CustomerOrder 1..* OrderItem · OrderItem *..1 Book, *..1 Store · Staff *..0..1 Store · Category 1..* Book
- **Main processes (DFD):** 1 Browse catalogue · 2 Manage cart · 3 Place order (allocate stock) · 4 Fulfil order · 5 Manage products · 6 Manage inventory · 7 Manage stores · 8 Manage staff accounts
